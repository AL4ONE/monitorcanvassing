import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useToast } from '../context/ToastContext';
import { useConfirm } from '../context/ConfirmContext';

export default function StaffDashboard() {
  const { showToast } = useToast();
  const { confirm } = useConfirm();
  const [stats, setStats] = useState(null);
  const [recentMessages, setRecentMessages] = useState([]);
  const [loading, setLoading] = useState(true);
  // Use local date (WIB), not UTC - toISOString() returns UTC which is wrong at midnight WIB
  const getLocalDate = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  };
  const [selectedDate, setSelectedDate] = useState(getLocalDate());
  const [deleting, setDeleting] = useState(null);

  useEffect(() => {
    fetchDashboard();
  }, [selectedDate]);

  const fetchDashboard = async () => {
    try {
      setLoading(true);
      const response = await api.get('/dashboard', {
        params: { date: selectedDate },
      });
      setStats(response.data);
      setRecentMessages(response.data.recent_messages || []);
    } catch (error) {
      console.error('Error fetching dashboard:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (messageId) => {
    const confirmed = await confirm({
      title: 'Hapus Laporan',
      message: 'Apakah Anda yakin ingin menghapus laporan ini?',
      confirmText: 'Ya, Hapus',
      cancelText: 'Batal',
      variant: 'danger'
    });
    if (!confirmed) return;

    try {
      setDeleting(messageId);
      await api.delete(`/messages/${messageId}`);
      showToast('Laporan berhasil dihapus', 'success');
      fetchDashboard(); // Refresh data
    } catch (error) {
      const errorMessage = error.response?.data?.message || 'Gagal menghapus laporan';
      showToast(errorMessage, 'error');
    } finally {
      setDeleting(null);
    }
  };

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  const targetsPerStage = stats?.targets_per_stage || {};

  // Helper function to format date to WIB
  const formatToWIB = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('id-ID', {
      timeZone: 'Asia/Jakarta',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  // Helper function to get stage label
  const getStageLabel = (stage) => {
    return stage === 0 ? 'Canvassing' : `Follow Up ${stage}`;
  };

  return (
    <div className="max-w-7xl mx-auto p-4 md:p-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4 md:mb-6">
        <h1 className="text-xl md:text-2xl font-bold">Dashboard</h1>
        <div className="flex flex-wrap gap-2 w-full sm:w-auto">
          <input
            type="date"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            className="border border-gray-300 rounded-md px-2 py-1.5 md:px-3 md:py-2 text-sm flex-1 sm:flex-none min-w-0"
          />
          <Link
            to="/upload"
            className="bg-indigo-600 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-md hover:bg-indigo-700 text-sm whitespace-nowrap text-center flex-1 sm:flex-none"
          >
            Upload Screenshot
          </Link>
        </div>
      </div>

      {/* Target Cards - Dynamic for all stages */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        {Object.entries(targetsPerStage).map(([stage, data]) => (
          <div key={stage} className="bg-white rounded-lg shadow-md p-4 md:p-6">
            <h2 className="text-base md:text-lg font-semibold mb-2 md:mb-4">{getStageLabel(parseInt(stage))}</h2>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-2xl md:text-3xl font-bold">
                  {data.count || 0} / {data.target || 50}
                </p>
                <p className="text-xs md:text-sm text-gray-600 mt-1 md:mt-2">
                  Target: {data.target || 50}/hari
                </p>
              </div>
              <div
                className={`text-3xl md:text-4xl ${data.met ? 'text-green-500' : 'text-red-500'
                  }`}
              >
                {data.met ? '✓' : '✗'}
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Recent Messages */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h2 className="text-lg font-semibold mb-4">Upload Terbaru</h2>
        {recentMessages.length === 0 ? (
          <p className="text-gray-500">Belum ada upload hari ini</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Stage
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Instagram Username
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Waktu Upload (WIB)
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Aksi
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {recentMessages.map((msg) => (
                  <tr key={msg.id}>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {msg.stage === 0 ? 'Canvassing' : `FU-${msg.stage}`}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      @{msg.canvassing_cycle?.prospect?.instagram_username || msg.ocr_instagram_username}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {formatToWIB(msg.submitted_at)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span
                        className={`px-2 py-1 text-xs rounded ${msg.validation_status === 'valid'
                          ? 'bg-green-100 text-green-800'
                          : msg.validation_status === 'invalid'
                            ? 'bg-red-100 text-red-800'
                            : 'bg-yellow-100 text-yellow-800'
                          }`}
                      >
                        {msg.validation_status === 'valid'
                          ? 'Valid'
                          : msg.validation_status === 'invalid'
                            ? 'Invalid'
                            : 'Pending'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                      {msg.validation_status === 'pending' && (
                        <button
                          onClick={() => handleDelete(msg.id)}
                          disabled={deleting === msg.id}
                          className="text-red-600 hover:text-red-800 font-medium disabled:opacity-50"
                        >
                          {deleting === msg.id ? 'Menghapus...' : 'Hapus'}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

