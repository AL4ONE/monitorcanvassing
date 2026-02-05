import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useToast } from '../context/ToastContext';
import { useConfirm } from '../context/ConfirmContext';

export default function CanvassingGroupList() {
  const { showToast } = useToast();
  const { confirm } = useConfirm();
  const [groups, setGroups] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [expandedGroups, setExpandedGroups] = useState({});

  useEffect(() => {
    fetchGroups();
  }, [statusFilter, search]);

  const fetchGroups = async () => {
    try {
      setLoading(true);
      const params = {};
      if (statusFilter) params.status = statusFilter;
      if (search) params.search = search;
      
      const response = await api.get('/canvassing-groups', { params });
      setGroups(response.data.data || []);
    } catch (error) {
      console.error('Error fetching groups:', error);
      showToast('Gagal memuat data: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id) => {
    const confirmed = await confirm({
      title: 'Hapus Group',
      message: 'Apakah Anda yakin ingin menghapus group ini? Semua data terkait akan ikut terhapus.',
      confirmText: 'Ya, Hapus',
      cancelText: 'Batal',
      variant: 'danger'
    });
    if (!confirmed) return;
    
    try {
      await api.delete(`/canvassing-groups/${id}`);
      showToast('Group berhasil dihapus', 'success');
      fetchGroups();
    } catch (error) {
      showToast('Gagal menghapus: ' + (error.response?.data?.message || error.message), 'error');
    }
  };

  const toggleExpand = (groupId) => {
    setExpandedGroups(prev => ({
      ...prev,
      [groupId]: !prev[groupId]
    }));
  };

  const getProgressColor = (percentage) => {
    if (percentage >= 80) return { bar: 'bg-green-500', text: 'text-green-600' };
    if (percentage >= 50) return { bar: 'bg-yellow-500', text: 'text-yellow-600' };
    return { bar: 'bg-red-500', text: 'text-red-600' };
  };

  const getStatusBadge = (status) => {
    const styles = {
      open: 'bg-blue-100 text-blue-800',
      on_progress: 'bg-yellow-100 text-yellow-800',
      closed: 'bg-green-100 text-green-800',
      cancelled: 'bg-red-100 text-red-800',
    };
    const labels = {
      open: 'Open',
      on_progress: 'Progress',
      closed: 'Closed',
      cancelled: 'Cancelled',
    };
    return (
      <span className={`px-2 py-1 text-xs rounded-full whitespace-nowrap ${styles[status] || 'bg-gray-100'}`}>
        {labels[status] || status}
      </span>
    );
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  };

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-7xl mx-auto p-4 md:p-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 className="text-xl md:text-2xl font-bold">Canvassing In Groups</h1>
        <Link
          to="/canvassing-groups/create"
          className="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 text-sm whitespace-nowrap"
        >
          + Buat Group Baru
        </Link>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-lg shadow-md p-4 mb-6 flex flex-col sm:flex-row gap-4">
        <input
          type="text"
          placeholder="Cari nama group..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="border border-gray-300 rounded-md px-3 py-2 flex-1 text-sm"
        />
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="border border-gray-300 rounded-md px-3 py-2 text-sm"
        >
          <option value="">Semua Status</option>
          <option value="open">Open</option>
          <option value="on_progress">On Progress</option>
          <option value="closed">Closed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {/* Groups - Desktop Table / Mobile Cards */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        {groups.length === 0 ? (
          <div className="p-6 text-center text-gray-500">
            Belum ada canvassing group
          </div>
        ) : (
          <>
            {/* Desktop Table View */}
            <div className="hidden lg:block overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Nama Group
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Kategori
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Tanggal
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Target/Progress
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Staff
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Status
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {groups.map((group) => {
                    const groupProgress = group.achievement_stats?.percentage || 0;
                    const progressColors = getProgressColor(groupProgress);
                    const isExpanded = expandedGroups[group.id];
                    
                    return (
                      <>
                        <tr key={group.id} className="hover:bg-gray-50">
                          <td className="px-4 py-4">
                            <div>
                              <div className="font-medium text-gray-900 text-sm">{group.name}</div>
                              <div className="text-xs text-gray-500 truncate max-w-[150px]">{group.city}{group.district ? `, ${group.district}` : ''}</div>
                            </div>
                          </td>
                          <td className="px-4 py-4 text-sm text-gray-500">
                            {group.category}
                          </td>
                          <td className="px-4 py-4 text-sm text-gray-500 whitespace-nowrap">
                            <div>{formatDate(group.start_date)}</div>
                            <div className="text-xs text-gray-400">{formatDate(group.end_date)}</div>
                          </td>
                          <td className="px-4 py-4">
                            <div className="text-sm">
                              <span className={`font-medium ${progressColors.text}`}>
                                {group.achievement_stats?.total_visits || 0}
                              </span>
                              <span className="text-gray-400"> / {group.total_target}</span>
                              <span className={`ml-1 text-xs font-bold ${progressColors.text}`}>
                                ({groupProgress.toFixed(0)}%)
                              </span>
                            </div>
                            <div className="w-20 bg-gray-200 rounded-full h-1.5 mt-1">
                              <div
                                className={`${progressColors.bar} h-1.5 rounded-full`}
                                style={{ width: `${Math.min(100, groupProgress)}%` }}
                              ></div>
                            </div>
                          </td>
                          <td className="px-4 py-4 text-sm">
                            <button
                              onClick={() => toggleExpand(group.id)}
                              className="flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium text-xs"
                            >
                              <span>{group.staff?.length || 0}</span>
                              <svg 
                                className={`w-3 h-3 transition-transform ${isExpanded ? 'rotate-180' : ''}`} 
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24"
                              >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                              </svg>
                            </button>
                          </td>
                          <td className="px-4 py-4">
                            {getStatusBadge(group.status)}
                          </td>
                          <td className="px-4 py-4 text-sm">
                            <div className="flex gap-1">
                              <Link
                                to={`/canvassing-groups/${group.id}`}
                                className="p-1.5 text-indigo-600 hover:bg-indigo-100 rounded"
                                title="Detail"
                              >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                              </Link>
                              <Link
                                to={`/canvassing-groups/${group.id}/edit`}
                                className="p-1.5 text-blue-600 hover:bg-blue-100 rounded"
                                title="Edit"
                              >
                               <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                              </Link>
                              <button
                                onClick={() => handleDelete(group.id)}
                                className="p-1.5 text-red-600 hover:bg-red-100 rounded"
                                title="Hapus"
                              >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                              </button>
                            </div>
                          </td>
                        </tr>
                        {/* Expanded Staff Row */}
                        {isExpanded && group.staff_stats && group.staff_stats.length > 0 && (
                          <tr key={`${group.id}-staff`} className="bg-gray-50">
                            <td colSpan="7" className="px-4 py-4">
                              <div className="ml-4">
                                <h4 className="text-sm font-semibold text-gray-700 mb-3">Progress Staff</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                  {group.staff_stats.map((staff) => {
                                    const staffProgress = staff.progress_percentage || 0;
                                    const staffColors = getProgressColor(staffProgress);
                                    
                                    return (
                                      <div key={staff.id} className="bg-white rounded-lg p-3 border border-gray-200">
                                        <div className="flex justify-between items-start mb-2">
                                          <div className="min-w-0 flex-1">
                                            <div className="font-medium text-sm text-gray-900 truncate">{staff.name}</div>
                                            <div className="text-xs text-gray-500 truncate">{staff.email}</div>
                                          </div>
                                          <span className={`text-sm font-bold ${staffColors.text} ml-2`}>
                                            {staffProgress.toFixed(0)}%
                                          </span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                          <div
                                            className={`${staffColors.bar} h-2 rounded-full`}
                                            style={{ width: `${Math.min(100, staffProgress)}%` }}
                                          ></div>
                                        </div>
                                        <div className="mt-2 flex justify-between text-xs text-gray-500">
                                          <span>Visit: <strong>{staff.total_visit}</strong> / {staff.target}</span>
                                          <span>({staff.total_days} hari)</span>
                                        </div>
                                      </div>
                                    );
                                  })}
                                </div>
                              </div>
                            </td>
                          </tr>
                        )}
                        {isExpanded && (!group.staff_stats || group.staff_stats.length === 0) && (
                          <tr key={`${group.id}-no-staff`} className="bg-gray-50">
                            <td colSpan="7" className="px-4 py-4 text-center text-gray-500 text-sm italic">
                              Belum ada staff ditugaskan
                            </td>
                          </tr>
                        )}
                      </>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {/* Mobile Card View */}
            <div className="lg:hidden divide-y divide-gray-200">
              {groups.map((group) => {
                const groupProgress = group.achievement_stats?.percentage || 0;
                const progressColors = getProgressColor(groupProgress);
                const isExpanded = expandedGroups[group.id];
                
                return (
                  <div key={group.id} className="p-4">
                    {/* Header Row */}
                    <div className="flex justify-between items-start mb-3">
                      <div className="min-w-0 flex-1">
                        <div className="font-semibold text-gray-900">{group.name}</div>
                        <div className="text-xs text-gray-500 truncate">{group.city}{group.district ? `, ${group.district}` : ''}{group.village ? `, ${group.village}` : ''}</div>
                      </div>
                      {getStatusBadge(group.status)}
                    </div>

                    {/* Info Grid */}
                    <div className="grid grid-cols-2 gap-2 text-sm mb-3">
                      <div>
                        <span className="text-gray-500">Kategori:</span>
                        <span className="ml-1 text-gray-900">{group.category}</span>
                      </div>
                      <div>
                        <span className="text-gray-500">Staff:</span>
                        <button
                          onClick={() => toggleExpand(group.id)}
                          className="ml-1 text-indigo-600 font-medium"
                        >
                          {group.staff?.length || 0} staff
                          <svg 
                            className={`w-3 h-3 inline ml-1 transition-transform ${isExpanded ? 'rotate-180' : ''}`} 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                          >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                          </svg>
                        </button>
                      </div>
                      <div className="col-span-2">
                        <span className="text-gray-500">Periode:</span>
                        <span className="ml-1 text-gray-900 text-xs">{formatDate(group.start_date)} - {formatDate(group.end_date)}</span>
                      </div>
                    </div>

                    {/* Progress Bar */}
                    <div className="mb-3">
                      <div className="flex justify-between items-center mb-1">
                        <span className="text-sm text-gray-600">Progress</span>
                        <span className={`text-sm font-bold ${progressColors.text}`}>
                          {group.achievement_stats?.total_visits || 0} / {group.total_target} ({groupProgress.toFixed(0)}%)
                        </span>
                      </div>
                      <div className="w-full bg-gray-200 rounded-full h-2">
                        <div
                          className={`${progressColors.bar} h-2 rounded-full`}
                          style={{ width: `${Math.min(100, groupProgress)}%` }}
                        ></div>
                      </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-2">
                      <Link
                        to={`/canvassing-groups/${group.id}`}
                        className="flex-1 text-center px-3 py-2 text-xs font-medium rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                      >
                        Detail
                      </Link>
                      <Link
                        to={`/canvassing-groups/${group.id}/edit`}
                        className="flex-1 text-center px-3 py-2 text-xs font-medium rounded bg-blue-100 text-blue-700 hover:bg-blue-200"
                      >
                        Edit
                      </Link>
                      <button
                        onClick={() => handleDelete(group.id)}
                        className="flex-1 text-center px-3 py-2 text-xs font-medium rounded bg-red-100 text-red-700 hover:bg-red-200"
                      >
                        Hapus
                      </button>
                    </div>

                    {/* Expanded Staff Section */}
                    {isExpanded && group.staff_stats && group.staff_stats.length > 0 && (
                      <div className="mt-4 pt-4 border-t border-gray-200">
                        <h4 className="text-sm font-semibold text-gray-700 mb-3">Progress Staff</h4>
                        <div className="space-y-3">
                          {group.staff_stats.map((staff) => {
                            const staffProgress = staff.progress_percentage || 0;
                            const staffColors = getProgressColor(staffProgress);
                            
                            return (
                              <div key={staff.id} className="bg-gray-50 rounded-lg p-3">
                                <div className="flex justify-between items-start mb-2">
                                  <div className="min-w-0 flex-1">
                                    <div className="font-medium text-sm text-gray-900 truncate">{staff.name}</div>
                                  </div>
                                  <span className={`text-sm font-bold ${staffColors.text}`}>
                                    {staffProgress.toFixed(0)}%
                                  </span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-1.5">
                                  <div
                                    className={`${staffColors.bar} h-1.5 rounded-full`}
                                    style={{ width: `${Math.min(100, staffProgress)}%` }}
                                  ></div>
                                </div>
                                <div className="mt-1 text-xs text-gray-500">
                                  Visit: {staff.total_visit} / {staff.target}
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    )}
                    {isExpanded && (!group.staff_stats || group.staff_stats.length === 0) && (
                      <div className="mt-4 pt-4 border-t border-gray-200 text-center text-gray-500 text-sm italic">
                        Belum ada staff ditugaskan
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

