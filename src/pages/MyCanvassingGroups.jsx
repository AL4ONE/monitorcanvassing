import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useToast } from '../context/ToastContext';

export default function MyCanvassingGroups() {
  const { showToast } = useToast();
  const [groups, setGroups] = useState([]);
  const [loading, setLoading] = useState(true);
  const [activeOnly, setActiveOnly] = useState(false); // Show all by default

  useEffect(() => {
    fetchMyGroups();
  }, [activeOnly]);

  // Check if current date is within assignment range
  const isAssignmentActive = (startDate, endDate) => {
    if (!startDate || !endDate) return false;
    const today = new Date().toISOString().split('T')[0];
    const start = startDate.split('T')[0];
    const end = endDate.split('T')[0];
    return today >= start && today <= end;
  };

  const fetchMyGroups = async () => {
    try {
      setLoading(true);
      const response = await api.get('/my-canvassing-groups', {
        params: { active_only: activeOnly }
      });
      setGroups(response.data.data || []);
    } catch (error) {
      console.error('Error fetching groups:', error);
      showToast('Gagal memuat data: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
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

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-7xl mx-auto p-4 md:p-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 className="text-xl md:text-2xl font-bold">Tugas Canvassing Saya</h1>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={activeOnly}
            onChange={(e) => setActiveOnly(e.target.checked)}
            className="rounded border-gray-300"
          />
          <span className="text-sm text-gray-700 whitespace-nowrap">Aktif saja</span>
        </label>
      </div>

      {groups.length === 0 ? (
        <div className="bg-white rounded-lg shadow-md p-6 text-center text-gray-500">
          Belum ada tugas canvassing yang ditugaskan kepada Anda
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {groups.map((group) => {
            const isActive = isAssignmentActive(group.pivot?.assigned_start_date, group.pivot?.assigned_end_date);
            const isGroupActive = group.status === 'open' || group.status === 'on_progress';

            return (
              <div
                key={group.id}
                className={`bg-white rounded-lg shadow-md overflow-hidden ${
                  isActive ? 'ring-2 ring-indigo-500' : ''
                }`}
              >
                {/* Header */}
                <div className={`${
                  group.status === 'cancelled' || group.status === 'closed' ? 'bg-gray-500' : 'bg-indigo-600'
                } text-white p-4`}>
                  <div className="flex justify-between items-start">
                    <div>
                      <h3 className="font-semibold text-lg">{group.name}</h3>
                      <p className="text-indigo-100 text-sm">{group.category}</p>
                    </div>
                    {getStatusBadge(group.status)}
                  </div>
                </div>

                {/* Body */}
                <div className="p-4 space-y-4">
                  {/* Location */}
                  <div className="flex items-center gap-2 text-gray-500 text-sm">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {group.city}{group.district ? `, ${group.district}` : ''}
                  </div>

                  {/* Period */}
                  <div className="flex items-center gap-2 text-gray-500 text-sm">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {formatDate(group.pivot?.assigned_start_date)} - {formatDate(group.pivot?.assigned_end_date)}
                  </div>

                  {/* Assignment Status Info */}
                  {!isActive && isGroupActive && (
                    <div className="bg-yellow-50 text-yellow-800 text-xs p-2 rounded border border-yellow-200">
                      Anda belum dijadwalkan untuk tugas ini hari ini.
                    </div>
                  )}

                  {/* My Stats */}
                  <div className="border-t pt-4">
                    <p className="text-sm text-gray-500 mb-2">Progress Saya</p>
                    <div className="grid grid-cols-3 gap-2 text-center">
                      <div className="bg-gray-50 rounded p-2">
                        <p className="text-lg font-bold">{group.my_stats?.total_visits || 0}</p>
                        <p className="text-xs text-gray-500">Visit</p>
                      </div>
                      <div className="bg-green-50 rounded p-2">
                        <p className="text-lg font-bold text-green-600">{group.my_stats?.registered || 0}</p>
                        <p className="text-xs text-gray-500">Registered</p>
                      </div>
                      <div className="bg-yellow-50 rounded p-2">
                        <p className="text-lg font-bold text-yellow-600">{group.my_stats?.on_progress || 0}</p>
                        <p className="text-xs text-gray-500">On Progress</p>
                      </div>
                    </div>
                  </div>

                  {/* Target Info */}
                  <div className="border-t pt-4">
                    <p className="text-sm text-gray-500 mb-1">Target: {group.target_per_day} merchant/hari</p>
                    <p className="text-sm text-gray-500">Total: {group.total_target} merchant</p>
                  </div>
                </div>

                {/* Footer - Start Button */}
                {isActive && isGroupActive ? (
                  <div className="p-4 bg-gray-50 border-t">
                    <Link
                      to={`/canvassing-execution/${group.id}`}
                      className="block text-center py-2 rounded-md font-medium bg-indigo-600 text-white hover:bg-indigo-700"
                    >
                      Mulai Canvassing
                    </Link>
                  </div>
                ) : (
                  <div className="p-4 bg-gray-50 border-t flex justify-center text-gray-400 text-sm italic">
                    {!isGroupActive ? 'Group Tidak Aktif' : 
                     !isActive ? 'Belum Jadwalnya' : ''}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
