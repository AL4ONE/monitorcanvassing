import { useState, useEffect } from 'react';
import api from '../api';

export default function QualityCheck() {
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
      alert('Gagal melakukan review');
    } finally {
      setReviewing(false);
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
        <div className="bg-white rounded-lg shadow-md p-6">
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
                  <div className="flex justify-between items-start">
                    <div>
                      <p className="font-medium">
                        {msg.stage === 0 ? 'Canvassing' : `FU-${msg.stage}`}
                      </p>
                      <p className="text-sm text-gray-600">
                        @{msg.canvassing_cycle?.prospect?.instagram_username || msg.ocr_instagram_username}
                      </p>
                      <p className="text-xs text-gray-500">
                        {msg.category === 'umkm_fb' ? 'UMKM F&B' : 
                         msg.category === 'coffee_shop' ? 'Coffee Shop' : 
                         msg.category === 'restoran' ? 'Restoran' : 'N/A'}
                      </p>
                      <p className="text-xs text-gray-500">
                        {new Date(msg.submitted_at).toLocaleString('id-ID')}
                      </p>
                    </div>
                    <span className="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">
                      Pending
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Message Detail */}
        <div className="bg-white rounded-lg shadow-md p-6">
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

              <div className="space-y-3 mb-6">
                <div>
                  <label className="text-sm font-medium text-gray-700">Stage</label>
                  <p className="text-sm">
                    {selectedMessage.data.stage === 0
                      ? 'Canvassing'
                      : `Follow Up ${selectedMessage.data.stage}`}
                  </p>
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">Instagram Username</label>
                  <p className="text-sm">
                    @{selectedMessage.data.canvassing_cycle?.prospect?.instagram_username || selectedMessage.data.ocr_instagram_username}
                  </p>
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">OCR Message</label>
                  <p className="text-sm text-gray-600">
                    {selectedMessage.data.ocr_message_snippet || 'Tidak ada'}
                  </p>
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">Kategori</label>
                  <p className="text-sm">
                    {selectedMessage.data.category === 'umkm_fb' ? 'UMKM F&B' : 
                     selectedMessage.data.category === 'coffee_shop' ? 'Coffee Shop' : 
                     selectedMessage.data.category === 'restoran' ? 'Restoran' : 'N/A'}
                  </p>
                </div>

                <div>
                  <label className="text-sm font-medium text-gray-700">Staff</label>
                  <p className="text-sm">
                    {selectedMessage.data.canvassing_cycle?.staff?.name}
                  </p>
                </div>

                {/* Timeline */}
                {selectedMessage.data.canvassing_cycle?.messages && (
                  <div>
                    <label className="text-sm font-medium text-gray-700">Timeline</label>
                    <div className="mt-2 space-y-2">
                      {selectedMessage.data.canvassing_cycle.messages.map((m) => (
                        <div
                          key={m.id}
                          className={`text-xs p-2 rounded ${
                            m.id === selectedMessage.data.id
                              ? 'bg-indigo-100'
                              : 'bg-gray-50'
                          }`}
                        >
                          {m.stage === 0 ? 'Canvassing' : `FU-${m.stage}`} -{' '}
                          {new Date(m.submitted_at).toLocaleDateString('id-ID')}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>

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

