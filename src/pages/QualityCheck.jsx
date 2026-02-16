import { useState, useEffect } from 'react';
import api from '../api';
import { useToast } from '../context/ToastContext';
import { useConfirm } from '../context/ConfirmContext';

export default function QualityCheck() {
  const { showToast } = useToast();
  const { confirm } = useConfirm();
  const [messages, setMessages] = useState([]);
  const [selectedMessage, setSelectedMessage] = useState(null);
  const [loading, setLoading] = useState(true);
  const [reviewing, setReviewing] = useState(false);
  const [notes, setNotes] = useState('');

  // Filter states
  const [filters, setFilters] = useState({
    stage: '',
    username: '',
    dateFrom: '',
    dateTo: '',
    category: '',
  });

  useEffect(() => {
    fetchMessages();
  }, [filters]);

  const fetchMessages = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();

      if (filters.stage !== '') {
        params.append('stage', filters.stage);
      }
      if (filters.username !== '') {
        params.append('username', filters.username);
      }
      if (filters.dateFrom !== '') {
        params.append('date_from', filters.dateFrom);
      }
      if (filters.dateTo !== '') {
        params.append('date_to', filters.dateTo);
      }
      if (filters.category !== '') {
        params.append('category', filters.category);
      }

      const queryString = params.toString();
      const url = `/quality-checks${queryString ? '?' + queryString : ''}`;
      const response = await api.get(url);
      setMessages(response.data.data || []);
    } catch (error) {
      console.error('Error fetching messages:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key, value) => {
    setFilters(prev => ({
      ...prev,
      [key]: value,
    }));
  };

  const clearFilters = () => {
    setFilters({
      stage: '',
      username: '',
      dateFrom: '',
      dateTo: '',
      category: '',
    });
  };

  const handleViewMessage = async (id) => {
    try {
      const response = await api.get(`/quality-checks/${id}`);
      setSelectedMessage(response.data);
    } catch (error) {
      console.error('Error fetching message detail:', error);
    }
  };

  const handleReview = async (status) => {
    if (!selectedMessage) return;

    try {
      setReviewing(true);
      await api.post(`/quality-checks/${selectedMessage.data.id}/review`, {
        status,
        notes,
      });

      setSelectedMessage(null);
      setNotes('');
      fetchMessages();
    } catch (error) {
      console.error('Error reviewing message:', error);
      showToast('Gagal melakukan review', 'error');
    } finally {
      setReviewing(false);
    }
  };

  const handleApproveAll = async () => {
    const confirmed = await confirm({
      title: 'Approve All',
      message: 'Apakah Anda yakin ingin menyetujui SEMUA pesan yang pending?',
      confirmText: 'Ya, Approve Semua',
      cancelText: 'Batal',
      variant: 'warning'
    });
    if (!confirmed) return;

    try {
      setLoading(true);
      const response = await api.post('/quality-checks/approve-all');
      showToast(response.data.message, 'success');
      fetchMessages();
    } catch (error) {
      console.error('Error approving all:', error);
      showToast('Gagal melakukan approve all: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-7xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-6">Quality Check</h1>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Message List */}
        <div className="bg-white rounded-lg shadow-md p-6 max-h-[calc(100vh-8rem)] overflow-y-auto">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-semibold">Pending Review</h2>
            {(filters.stage !== '' || filters.username !== '' || filters.dateFrom !== '' || filters.dateTo !== '' || filters.category !== '') && (
              <button
                onClick={clearFilters}
                className="text-sm text-indigo-600 hover:text-indigo-800"
              >
                Clear Filters
              </button>
            )}
            {messages.length > 0 && (
              <button
                onClick={handleApproveAll}
                className="ml-4 bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
              >
                Approve All
              </button>
            )}
          </div>

          {/* Filters */}
          <div className="mb-4 space-y-3 p-4 bg-gray-50 rounded-lg">
            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Jenis Aktivitas
              </label>
              <select
                value={filters.stage}
                onChange={(e) => handleFilterChange('stage', e.target.value)}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
              >
                <option value="">Semua</option>
                <option value="0">Canvassing</option>
                <option value="1">Follow Up 1</option>
                <option value="2">Follow Up 2</option>
                <option value="3">Follow Up 3</option>
                <option value="4">Follow Up 4</option>
                <option value="5">Follow Up 5</option>
                <option value="6">Follow Up 6</option>
                <option value="7">Follow Up 7</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Instagram Username
              </label>
              <input
                type="text"
                value={filters.username}
                onChange={(e) => handleFilterChange('username', e.target.value)}
                placeholder="Cari username..."
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-gray-700 mb-1">
                Kategori
              </label>
              <select
                value={filters.category}
                onChange={(e) => handleFilterChange('category', e.target.value)}
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
              >
                <option value="">Semua</option>
                <option value="umkm_fb">UMKM F&B</option>
                <option value="coffee_shop">Coffee Shop</option>
                <option value="restoran">Restoran</option>
              </select>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Dari Tanggal
                </label>
                <input
                  type="date"
                  value={filters.dateFrom}
                  onChange={(e) => handleFilterChange('dateFrom', e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">
                  Sampai Tanggal
                </label>
                <input
                  type="date"
                  value={filters.dateTo}
                  onChange={(e) => handleFilterChange('dateTo', e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                />
              </div>
            </div>
          </div>
          {messages.length === 0 ? (
            <p className="text-gray-500">Tidak ada message yang perlu direview</p>
          ) : (
            <div className="space-y-4">
              {messages.map((msg) => (
                <div
                  key={msg.id}
                  className="border rounded-lg p-4 cursor-pointer hover:bg-gray-50"
                  onClick={() => handleViewMessage(msg.id)}
                >
                    <div>
                      <div className="flex justify-between items-start">
                        <div>
                          <p className="font-medium text-indigo-600">
                            {msg.stage === 0 ? 'Canvassing' : `FU-${msg.stage}`}
                          </p>
                          <p className="text-sm font-semibold text-gray-900">
                            @{msg.canvassing_cycle?.prospect?.instagram_username || msg.ocr_instagram_username}
                          </p>
                          <p className="text-xs text-gray-500 mt-1">
                            {msg.category === 'umkm_fb' ? 'UMKM F&B' :
                              msg.category === 'coffee_shop' ? 'Coffee Shop' :
                                msg.category === 'restoran' ? 'Restoran' :
                                  msg.category === 'product_digital' ? 'Product Digital' : 'N/A'}
                          </p>
                          
                   
                          {/* New Info Fields in List */}
                          <div className="mt-2 text-xs text-gray-600 space-y-1">
                                <div className="flex gap-2">
                                    <span className="font-medium">Kontak:</span> {msg.contact_number || '-'}
                                </div>
                                <div className="flex gap-2">
                                    <span className="font-medium">Channel:</span> {msg.channel || '-'}
                                </div>
                                {msg.interaction_status && (
                                    <div className="flex gap-2">
                                        <span className="font-medium">Status:</span> 
                                        {(() => {
                                            const status = msg.canvassing_cycle?.latest_message?.interaction_status || msg.interaction_status;
                                            return (
                                                <span className={`px-1.5 rounded ${
                                                    status === 'menerima' ? 'bg-green-100 text-green-700' :
                                                    status === 'menolak' ? 'bg-red-100 text-red-700' :
                                                    'bg-yellow-100 text-yellow-700'
                                                }`}>
                                                    {status}
                                                </span>
                                            );
                                        })()}
                                    </div>
                                )}
                                {msg.has_website && (
                                     <div className="flex gap-2 items-center text-blue-600">
                                        <span className="font-medium text-gray-600">Web:</span> 
                                        <a href={msg.website_url} target="_blank" rel="noopener noreferrer" className="hover:underline truncate max-w-[150px]" onClick={e => e.stopPropagation()}>
                                            {msg.website_url}
                                        </a>
                                     </div>
                                )}
                          </div>
                          
                          <p className="text-xs text-gray-400 mt-2">
                            {new Date(msg.submitted_at).toLocaleString('id-ID')}
                          </p>
                        </div>
                        <span className="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded shrink-0 ml-2">
                          Pending
                        </span>
                      </div>
                    </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Message Detail */}
        <div className="bg-white rounded-lg shadow-md p-6 sticky top-24 self-start max-h-[calc(100vh-8rem)] overflow-y-auto">
          {selectedMessage ? (
            <>
              <h2 className="text-lg font-semibold mb-4">Detail Message</h2>

              <div className="mb-4">
                {selectedMessage.screenshot_url ? (
                  <img
                    src={selectedMessage.screenshot_url}
                    alt="Screenshot"
                    className="max-w-full h-auto rounded-lg border border-gray-300 mb-4"
                    onError={(e) => {
                      console.error('Image load error:', selectedMessage.screenshot_url);
                      e.target.style.display = 'none';
                      e.target.nextSibling.style.display = 'block';
                    }}
                  />
                ) : null}
                <div style={{ display: 'none' }} className="text-red-500 text-sm">
                  Gagal memuat gambar. Pastikan storage link sudah dibuat.
                </div>
              </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-xs text-gray-500">Instagram Username</p>
                      <p className="font-medium">
                        @{selectedMessage.data.canvassing_cycle?.prospect?.instagram_username || selectedMessage.data.ocr_instagram_username}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Kategori</p>
                      <p className="font-medium">
                        {selectedMessage.data.category === 'umkm_fb' ? 'UMKM F&B' :
                          selectedMessage.data.category === 'coffee_shop' ? 'Coffee Shop' :
                            selectedMessage.data.category === 'restoran' ? 'Restoran' :
                              selectedMessage.data.category === 'product_digital' ? 'Product Digital' : 'N/A'}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Tanggal Upload</p>
                      <p className="font-medium">
                        {new Date(selectedMessage.data.submitted_at).toLocaleString('id-ID')}
                      </p>
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">Kontak</p>
                        <p className="font-medium">{selectedMessage.data.contact_number || '-'}</p>
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">Channel</p>
                        <p className="font-medium">
                            {selectedMessage.data.channel ? (
                                <>
                                    {selectedMessage.data.channel}
                                    {selectedMessage.data.channel_category && <span className="text-xs text-gray-500 ml-1">({selectedMessage.data.channel_category})</span>}
                                </>
                            ) : '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-gray-500">Lokasi</p>
                        <p className="font-medium truncate" title={selectedMessage.data.lokasi}>{selectedMessage.data.lokasi || '-'}</p>
                    </div>
                     <div>
                        <p className="text-xs text-gray-500">Status Interaksi</p>
                        {(() => {
                             const status = selectedMessage.data.canvassing_cycle?.latest_message?.interaction_status || selectedMessage.data.interaction_status;
                             return (
                                <p className={`font-medium inline-block px-2 py-0.5 rounded text-sm ${
                                     status === 'menerima' ? 'bg-green-100 text-green-700' :
                                     status === 'menolak' ? 'bg-red-100 text-red-700' :
                                     status ? 'bg-yellow-100 text-yellow-700' : 'text-gray-500'
                                }`}>
                                    {status || '-'}
                                </p>
                             );
                        })()}
                    </div>
                  </div>
                  
                  {/* Website Info */}
                  <div className="mt-4 border-t pt-3">
                     <p className="text-xs text-gray-500 mb-2 font-semibold">Informasi Website</p>
                     <div className="grid grid-cols-2 gap-4">
                        <div>
                            <p className="text-xs text-gray-500">Punya Website?</p>
                            <p className="font-medium">{selectedMessage.data.has_website ? 'Ya' : 'Tidak'}</p>
                        </div>
                         {selectedMessage.data.has_website && (
                            <div className="col-span-2">
                                <p className="text-xs text-gray-500">URL Website</p>
                                <a href={selectedMessage.data.website_url} target="_blank" rel="noopener noreferrer" className="font-medium text-blue-600 hover:underline break-all">
                                    {selectedMessage.data.website_url}
                                </a>
                            </div>
                         )}
                         <div>
                            <p className="text-xs text-gray-500">Punya Payment Gateway?</p>
                            <p className="font-medium">{selectedMessage.data.has_payment_gateway ? 'Ya' : 'Tidak'}</p>
                        </div>
                     </div>
                  </div>

                  {selectedMessage.data.canvassing_cycle?.prospect?.instagram_link && (
                    <div className="mt-4">
                      <a
                        href={selectedMessage.data.canvassing_cycle.prospect.instagram_link}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-blue-600 hover:underline text-sm flex items-center gap-1"
                      >
                        Buka Profil Instagram ↗
                      </a>
                    </div>
                  )}

              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Catatan
                </label>
                <textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                  rows="3"
                  placeholder="Tambahkan catatan (opsional)"
                />
              </div>

              <div className="flex gap-4">
                <button
                  onClick={() => handleReview('approved')}
                  disabled={reviewing}
                  className="flex-1 bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 disabled:opacity-50"
                >
                  Approve
                </button>
                <button
                  onClick={() => handleReview('rejected')}
                  disabled={reviewing}
                  className="flex-1 bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 disabled:opacity-50"
                >
                  Reject
                </button>
              </div>
            </>
          ) : (
            <div className="text-center text-gray-500 py-12">
              Pilih message untuk melihat detail
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

