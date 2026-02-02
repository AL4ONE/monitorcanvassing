import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import api from '../api';

export default function CanvassingGroupDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [group, setGroup] = useState(null);
  const [dailyStats, setDailyStats] = useState([]);
  const [staffStats, setStaffStats] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [availableStaff, setAvailableStaff] = useState([]);
  const [assignForm, setAssignForm] = useState({
    staff_id: '',
    assigned_start_date: '',
    assigned_end_date: '',
  });
  const [assigning, setAssigning] = useState(false);
  const [selectedImage, setSelectedImage] = useState(null);

  useEffect(() => {
    fetchGroup();
  }, [id]);

  const fetchGroup = async () => {
    try {
      setLoading(true);
      const response = await api.get(`/canvassing-groups/${id}`);
      setGroup(response.data.data);
      setDailyStats(response.data.daily_stats || []);
      setStaffStats(response.data.staff_stats || []);
    } catch (error) {
      alert('Gagal memuat data: ' + (error.response?.data?.message || error.message));
      navigate('/canvassing-groups');
    } finally {
      setLoading(false);
    }
  };

  const fetchAvailableStaff = async () => {
    try {
      const params = {};
      if (assignForm.assigned_start_date) params.start_date = assignForm.assigned_start_date;
      if (assignForm.assigned_end_date) params.end_date = assignForm.assigned_end_date;
      
      const response = await api.get(`/canvassing-groups/${id}/available-staff`, { params });
      setAvailableStaff(response.data.data || []);
    } catch (error) {
      console.error('Error fetching available staff:', error);
    }
  };

  const openAssignModal = () => {
    setAssignForm({
      staff_id: '',
      assigned_start_date: group?.start_date ? new Date(group.start_date).toLocaleDateString('en-CA') : '',
      assigned_end_date: group?.end_date ? new Date(group.end_date).toLocaleDateString('en-CA') : '',
    });
    setShowAssignModal(true);
    fetchAvailableStaff();
  };

  const handleAssignDateChange = (e) => {
    const { name, value } = e.target;
    setAssignForm((prev) => {
      const updated = { ...prev, [name]: value };
      return updated;
    });
  };

  useEffect(() => {
    if (showAssignModal && assignForm.assigned_start_date && assignForm.assigned_end_date) {
      fetchAvailableStaff();
    }
  }, [assignForm.assigned_start_date, assignForm.assigned_end_date]);

  const handleAssignStaff = async () => {
    if (!assignForm.staff_id || !assignForm.assigned_start_date || !assignForm.assigned_end_date) {
      alert('Mohon isi semua field');
      return;
    }

    try {
      setAssigning(true);
      await api.post(`/canvassing-groups/${id}/assign-staff`, assignForm);
      alert('Staff berhasil ditugaskan');
      setShowAssignModal(false);
      fetchGroup();
    } catch (error) {
      alert('Gagal menugaskan staff: ' + (error.response?.data?.message || error.message));
    } finally {
      setAssigning(false);
    }
  };

  const handleRemoveStaff = async (staffId, staffName) => {
    if (!confirm(`Hapus ${staffName} dari group ini?`)) return;

    try {
      await api.delete(`/canvassing-groups/${id}/remove-staff/${staffId}`);
      alert('Staff berhasil dihapus');
      fetchGroup();
    } catch (error) {
      alert('Gagal menghapus staff: ' + (error.response?.data?.message || error.message));
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
      on_progress: 'On Progress',
      closed: 'Closed',
      cancelled: 'Cancelled',
    };
    return (
      <span className={`px-3 py-1 text-sm rounded-full ${styles[status] || 'bg-gray-100'}`}>
        {labels[status] || status}
      </span>
    );
  };

  const openImageModal = (imageUrl) => {
    setSelectedImage(imageUrl);
  };

  const closeImageModal = () => {
    setSelectedImage(null);
  };

  if (loading || !group) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-7xl mx-auto p-6">
      {/* Header */}
      <div className="flex justify-between items-start mb-6">
        <div>
          <Link to="/canvassing-groups" className="text-gray-500 hover:text-indigo-600 mb-2 inline-flex items-center gap-1 transition-colors duration-200">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
          </Link>
          <h1 className="text-2xl font-bold">{group.name}</h1>
          <p className="text-gray-500">{group.city}{group.district ? `, ${group.district}` : ''}</p>
        </div>
        <div className="flex gap-2">
          {getStatusBadge(group.status)}
          <Link
            to={`/canvassing-groups/${id}/edit`}
            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm hover:shadow transition-all duration-200"
          >
            <svg className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit Group
          </Link>
        </div>
      </div>

      {/* Info Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-lg shadow-md p-4">
          <h3 className="text-sm text-gray-500">Kategori</h3>
          <p className="text-xl font-bold">{group.category}</p>
        </div>
        <div className="bg-white rounded-lg shadow-md p-4">
          <h3 className="text-sm text-gray-500">Periode</h3>
          <p className="text-sm font-medium">{formatDate(group.start_date)} - {formatDate(group.end_date)}</p>
          <p className="text-xs text-gray-400">{group.total_days} hari</p>
        </div>
        <div className="bg-white rounded-lg shadow-md p-4">
          <h3 className="text-sm text-gray-500">Target Total</h3>
          <p className="text-xl font-bold">{group.total_target} merchant</p>
          <p className="text-xs text-gray-400">{group.target_per_day}/hari per staff</p>
        </div>
        <div className="bg-white rounded-lg shadow-md p-4">
          <h3 className="text-sm text-gray-500">Progress Group</h3>
          <p className="text-xl font-bold text-green-600">
            {group.achievement_stats?.total_visits || 0}
            <span className="text-gray-400 text-sm font-normal"> / {group.total_target}</span>
          </p>
          <div className="w-full bg-gray-200 rounded-full h-2 mt-2">
            <div
              className="bg-green-600 h-2 rounded-full"
              style={{ width: `${Math.min(100, group.achievement_stats?.percentage || 0)}%` }}
            ></div>
          </div>
        </div>
      </div>

      {/* Staff Stats Section */}
      <div className="bg-white rounded-lg shadow-md p-6 mb-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold">Performa Staff ({staffStats?.length || 0})</h2>
          <button
            onClick={openAssignModal}
            className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm hover:shadow transition-all duration-200"
          >
            <svg className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
            </svg>
            Tambah Staff
          </button>
        </div>

        {staffStats?.length === 0 ? (
          <p className="text-gray-500 text-center py-4">Belum ada staff ditugaskan</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Hari</th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Visit</th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Reg</th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">On Prog</th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Target</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-1/4">Progress</th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {staffStats?.map((staff) => (
                  <tr key={staff.id}>
                    <td className="px-4 py-3">
                      <div className="text-sm font-medium text-gray-900">{staff.name}</div>
                      <div className="text-xs text-gray-500">{staff.email}</div>
                    </td>
                    <td className="px-4 py-3 text-sm text-gray-500">
                      {formatDate(staff.assigned_start_date)} - <br/>{formatDate(staff.assigned_end_date)}
                    </td>
                    <td className="px-4 py-3 text-sm text-center">{staff.total_days}</td>
                    <td className="px-4 py-3 text-sm text-center font-bold">{staff.total_visit}</td>
                    <td className="px-4 py-3 text-sm text-center text-green-600 font-bold">{staff.registered}</td>
                    <td className="px-4 py-3 text-sm text-center text-yellow-600">{staff.on_progress}</td>
                    <td className="px-4 py-3 text-sm text-center">{staff.target}</td>
                    <td className="px-4 py-3 align-middle">
                      <div className="w-full bg-gray-200 rounded-full h-2.5">
                        <div 
                          className="bg-green-600 h-2.5 rounded-full" 
                          style={{ width: `${Math.min(100, staff.progress_percentage)}%` }}
                        ></div>
                      </div>
                      <div className="text-xs text-gray-500 mt-1 text-right">{staff.progress_percentage}%</div>
                    </td>
                    <td className="px-4 py-3 text-center">
                      <button
                        onClick={() => handleRemoveStaff(staff.id, staff.name)}
                        className="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200"
                        title="Hapus Staff"
                      >
                         <svg className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Hapus
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Daily Stats Table */}
      <div className="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 className="text-lg font-semibold mb-4">Statistik Harian ({dailyStats.length} hari)</h2>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hari</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total Visit</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">On Progress</th>
                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Target</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {dailyStats.map((day) => (
                <tr key={day.date}>
                  <td className="px-4 py-3 text-sm">{formatDate(day.date)}</td>
                  <td className="px-4 py-3 text-sm text-gray-500">{day.day}</td>
                  <td className="px-4 py-3 text-sm font-medium">{day.total_visit}</td>
                  <td className="px-4 py-3 text-sm font-medium text-green-600">{day.registered}</td>
                  <td className="px-4 py-3 text-sm font-medium text-yellow-600">{day.on_progress}</td>
                  <td className="px-4 py-3 text-sm text-gray-500">{day.target}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Prospects Table */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h2 className="text-lg font-semibold mb-4">Daftar Prospect ({group.prospects?.length || 0})</h2>
        {group.prospects?.length === 0 ? (
          <p className="text-gray-500 text-center py-4">Belum ada prospect</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Foto</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama Usaha</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">PIC</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kontak</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {group.prospects?.map((prospect) => (
                  <tr key={prospect.id}>
                    <td className="px-4 py-3">
                      {prospect.photo_url ? (
                        <img
                          src={prospect.photo_url}
                          alt="Thumbnail"
                          className="w-10 h-10 object-cover rounded cursor-pointer hover:opacity-80 transition-opacity"
                          onClick={() => openImageModal(prospect.photo_url)}
                        />
                      ) : (
                        <div className="w-10 h-10 bg-gray-200 rounded flex items-center justify-center text-gray-400">
                          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-3 text-sm font-medium">{prospect.business_name}</td>
                    <td className="px-4 py-3 text-sm text-gray-500">{prospect.contact_name || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-500">{prospect.contact_number || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-500">{prospect.staff?.name}</td>
                    <td className="px-4 py-3 text-sm text-gray-500">{formatDate(prospect.visit_date)}</td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-1 text-xs rounded-full ${
                        prospect.status === 'registered' 
                          ? 'bg-green-100 text-green-800' 
                          : 'bg-yellow-100 text-yellow-800'
                      }`}>
                        {prospect.status === 'registered' ? 'Registered' : 'On Progress'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Image Zoom Modal */}
      {selectedImage && (
        <div 
          className="fixed inset-0 z-[100] bg-black bg-opacity-90 flex items-center justify-center p-4"
          onClick={closeImageModal}
        >
          <div className="relative max-w-4xl max-h-[90vh]">
            <button 
              className="absolute -top-10 right-0 text-white hover:text-gray-300 text-3xl font-bold"
              onClick={closeImageModal}
            >
              &times;
            </button>
            <img 
              src={selectedImage} 
              alt="Zoomed" 
              className="max-w-full max-h-[85vh] rounded-lg shadow-2xl"
              onClick={(e) => e.stopPropagation()} 
            />
          </div>
        </div>
      )}

      {/* Assign Staff Modal */}
      {showAssignModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 className="text-lg font-semibold mb-4">Tambah Staff</h2>
            
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Tanggal Mulai
                  </label>
                  <input
                    type="date"
                    name="assigned_start_date"
                    value={assignForm.assigned_start_date}
                    onChange={handleAssignDateChange}
                    min={group.start_date ? new Date(group.start_date).toLocaleDateString('en-CA') : ''}
                    max={group.end_date ? new Date(group.end_date).toLocaleDateString('en-CA') : ''}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Tanggal Selesai
                  </label>
                  <input
                    type="date"
                    name="assigned_end_date"
                    value={assignForm.assigned_end_date}
                    onChange={handleAssignDateChange}
                    min={assignForm.assigned_start_date || (group.start_date ? new Date(group.start_date).toLocaleDateString('en-CA') : '')}
                    max={group.end_date ? new Date(group.end_date).toLocaleDateString('en-CA') : ''}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Pilih Staff
                </label>
                <select
                  name="staff_id"
                  value={assignForm.staff_id}
                  onChange={(e) => setAssignForm((prev) => ({ ...prev, staff_id: e.target.value }))}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                >
                  <option value="">Pilih Staff</option>
                  {availableStaff.map((staff) => (
                    <option 
                      key={staff.id} 
                      value={staff.id}
                      disabled={!staff.available}
                    >
                      {staff.name} {!staff.available ? `(Conflict: ${staff.conflict?.group_name})` : ''}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="flex justify-end gap-4 mt-6 border-t border-gray-100 pt-4">
              <button
                onClick={() => setShowAssignModal(false)}
                className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200"
              >
                Batal
              </button>
              <button
                onClick={handleAssignStaff}
                disabled={assigning}
                className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium shadow-sm hover:shadow transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                {assigning ? 'Menyimpan...' : 'Tambah Staff'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
