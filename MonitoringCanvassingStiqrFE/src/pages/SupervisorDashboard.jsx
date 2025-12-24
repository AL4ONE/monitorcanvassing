import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';

export default function SupervisorDashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);

  useEffect(() => {
    fetchDashboard();
  }, [selectedDate]);

  const fetchDashboard = async () => {
    try {
      setLoading(true);
      const response = await api.get('/dashboard', {
        params: { date: selectedDate },
      });
      console.log('Dashboard data:', response.data); // Debug log
      setData(response.data);
    } catch (error) {
      console.error('Error fetching dashboard:', error);
      console.error('Error details:', error.response?.data); // Debug log
      alert('Gagal memuat dashboard: ' + (error.response?.data?.message || error.message));
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-7xl mx-auto p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Dashboard Supervisor</h1>
        <div className="flex gap-4">
          <input
            type="date"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            className="border border-gray-300 rounded-md px-3 py-2"
          />
          <Link
            to="/quality-check"
            className="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700"
          >
            Quality Check
          </Link>
        </div>
      </div>

      {/* Overall Stats */}
      {data?.overall_stats ? (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-sm font-medium text-gray-600">Total Staff</h3>
            <p className="text-3xl font-bold mt-2">{data.overall_stats.total_staff}</p>
          </div>
          <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-sm font-medium text-gray-600">Total Canvassing</h3>
            <p className="text-3xl font-bold mt-2">{data.overall_stats.total_canvassing}</p>
          </div>
          <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-sm font-medium text-gray-600">Total Follow Up</h3>
            <p className="text-3xl font-bold mt-2">{data.overall_stats.total_follow_up}</p>
          </div>
          <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-sm font-medium text-gray-600">Pending QC</h3>
            <p className="text-3xl font-bold mt-2">{data.overall_stats.pending_quality_checks}</p>
          </div>
        </div>
      ) : (
        <div className="bg-white rounded-lg shadow-md p-6 mb-8">
          <p className="text-gray-500">Tidak ada data untuk tanggal yang dipilih</p>
        </div>
      )}

      {/* Debug Info */}
      {!data && !loading && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
          <p className="text-yellow-800">
            Tidak ada data. Pastikan:
            <ul className="list-disc list-inside mt-2">
              <li>Sudah ada staff yang terdaftar</li>
              <li>Sudah ada message yang diupload untuk tanggal {new Date(selectedDate).toLocaleDateString('id-ID')}</li>
              <li>API endpoint berfungsi dengan baik</li>
            </ul>
          </p>
        </div>
      )}

      {/* Staff Stats */}
      {data?.staff_stats && data.staff_stats.length > 0 ? (
        <div className="space-y-6">
          {data.staff_stats.map((staffStat) => (
          <div key={staffStat.staff.id} className="bg-white rounded-lg shadow-md p-6">
            <div className="flex justify-between items-start mb-4">
              <div>
                <h2 className="text-xl font-semibold">{staffStat.staff.name}</h2>
                <p className="text-sm text-gray-600">{staffStat.staff.email}</p>
              </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2 mb-4">
              {staffStat.targets_per_stage && Object.entries(staffStat.targets_per_stage).map(([stage, targetData]) => (
                <div key={stage} className="border rounded-lg p-3">
                  <h3 className="text-xs font-medium text-gray-600 mb-1">
                    {stage === '0' ? 'Canvassing' : `FU-${stage}`}
                  </h3>
                  <div className="flex items-center justify-between">
                    <span className="text-lg font-bold">
                      {targetData.count || 0}/{targetData.target || 50}
                    </span>
                    <span
                      className={`text-sm ${
                        targetData.met ? 'text-green-500' : 'text-red-500'
                      }`}
                    >
                      {targetData.met ? '✓' : '✗'}
                    </span>
                  </div>
                </div>
              ))}
            </div>

            {/* Red Flags */}
            {staffStat.red_flags && staffStat.red_flags.length > 0 && (
              <div className="mt-4">
                <h3 className="text-sm font-medium text-red-600 mb-2">Red Flags:</h3>
                <ul className="list-disc list-inside space-y-1">
                  {staffStat.red_flags.map((flag, index) => (
                    <li key={index} className="text-sm text-red-600">
                      {flag.message}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
          ))}
        </div>
      ) : (
        <div className="bg-white rounded-lg shadow-md p-6">
          <p className="text-gray-500 text-center">
            Tidak ada staff yang terdaftar atau tidak ada data untuk tanggal {new Date(selectedDate).toLocaleDateString('id-ID')}
          </p>
        </div>
      )}
    </div>
  );
}

