import { useState, useEffect, useCallback } from 'react';
import api from '../api';

export default function SupervisorOnlineCanvassing() {
  const [reports, setReports] = useState([]);
  const [loading, setLoading] = useState(true);
  const [staffList, setStaffList] = useState([]);
  const [selectedImage, setSelectedImage] = useState(null);
  
  // Filters
  const [filters, setFilters] = useState({
    staff_id: '',
    date: new Date().toISOString().split('T')[0],
  });

  const fetchReports = useCallback(async () => {
    try {
      setLoading(true);
      const params = {};
      if (filters.staff_id) params.staff_id = filters.staff_id;
      if (filters.date) params.date = filters.date;
      params.per_page = 50;

      const res = await api.get('/online-canvassing', { params });
      setReports(res.data.data.data || []);
    } catch (error) {
      console.error('Error fetching reports:', error);
      // alert('Gagal memuat laporan');
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    fetchStaff();
    fetchReports();
  }, [fetchReports]);

  const fetchStaff = async () => {
    try {
      // Assuming there's an endpoint to get all staff, or we can use existing one
      // For now, using existing endpoint or leaving empty if not available
      const res = await api.get('/users?role=staff'); 
      setStaffList(res.data.data || []);
    } catch (error) {
      console.error('Error fetching staff:', error);
    }
  };

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters(prev => ({ ...prev, [name]: value }));
  };

  return (
    <div className="max-w-7xl mx-auto p-6">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h1 className="text-2xl font-bold">Laporan Canvassing Out Group (Direct)</h1>
          <p className="text-gray-500">Monitoring laporan staff tanpa group</p>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white p-4 rounded-lg shadow mb-6 flex gap-4 items-end">
        <div className="w-1/4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
          <input
            type="date"
            name="date"
            value={filters.date}
            onChange={handleFilterChange}
            className="w-full border border-gray-300 rounded-md px-3 py-2"
          />
        </div>
        <div className="w-1/4">
          <label className="block text-sm font-medium text-gray-700 mb-1">Filter Staff</label>
          <select
            name="staff_id"
            value={filters.staff_id}
            onChange={handleFilterChange}
            className="w-full border border-gray-300 rounded-md px-3 py-2"
          >
            <option value="">Semua Staff</option>
            {staffList.map(staff => (
              <option key={staff.id} value={staff.id}>{staff.name}</option>
            ))}
          </select>
        </div>
        <button 
          onClick={fetchReports}
          className="bg-indigo-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-indigo-700 h-[42px] shadow-sm hover:shadow transition-all duration-200 flex items-center gap-2"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Refresh
        </button>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        {loading ? (
          <div className="p-6 text-center">Memuat data...</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usaha</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alamat</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PIC & Kontak</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {reports.length === 0 ? (
                  <tr>
                    <td colSpan="8" className="px-6 py-12 text-center text-gray-500">
                      Tidak ada laporan pada tanggal ini
                    </td>
                  </tr>
                ) : (
                  reports.map((report) => (
                  <tr key={report.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap">
                      {new Date(report.visit_date).toLocaleDateString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-indigo-600">{report.staff?.name || '-'}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm font-medium text-gray-900">{report.business_name}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-gray-500 max-w-xs">{report.address || '-'}</div>
                    </td>
                    <td className="px-6 py-4">
                      {report.contact_name && <div className="text-sm text-gray-900">{report.contact_name}</div>}
                      {report.contact_number && <div className="text-sm text-gray-500">{report.contact_number}</div>}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                       <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                        ${report.status === 'registered' ? 'bg-green-100 text-green-800' : 
                          report.status === 'rejected' ? 'bg-red-100 text-red-800' :
                          'bg-yellow-100 text-yellow-800'}`}>
                        {report.status === 'registered' ? 'Registered' : 
                         report.status === 'rejected' ? 'Rejected' : 'On Progress'}
                      </span>
                      {report.status === 'rejected' && report.rejection_reason && (
                        <div className="text-xs text-red-600 mt-1 max-w-xs truncate" title={report.rejection_reason}>
                          {report.rejection_reason}
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                      {report.notes || '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {report.photo_url ? (
                        <img
                          src={report.photo_url}
                          alt="Proof"
                          className="h-10 w-10 rounded object-cover cursor-pointer hover:opacity-75"
                          onClick={() => setSelectedImage(report.photo_url)}
                        />
                      ) : (
                        <span className="text-gray-400">-</span>
                      )}
                    </td>
                  </tr>
                ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

       {/* Image Zoom Modal */}
       {selectedImage && (
        <div 
          className="fixed inset-0 z-[100] bg-black bg-opacity-90 flex items-center justify-center p-4"
          onClick={() => setSelectedImage(null)}
        >
          <div className="relative max-w-4xl max-h-[90vh]">
            <button 
              className="absolute -top-10 right-0 text-white hover:text-gray-300 text-3xl font-bold"
              onClick={() => setSelectedImage(null)}
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
    </div>
  );
}
