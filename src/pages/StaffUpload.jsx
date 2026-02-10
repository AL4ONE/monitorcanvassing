import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';

export default function StaffUpload() {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });
  const [errors, setErrors] = useState([]);
  const [selectedStage, setSelectedStage] = useState(0);
  const [selectedCategory, setSelectedCategory] = useState('');
  const [contact, setContact] = useState('');
  const [instagramLink, setInstagramLink] = useState('');
  const [channel, setChannel] = useState('');
  const [interactionStatus, setInteractionStatus] = useState('');
  const [lokasi, setLokasi] = useState('');
  const [activeProspects, setActiveProspects] = useState([]);
  const [selectedProspect, setSelectedProspect] = useState('');
  const [ocrFailed, setOcrFailed] = useState(false);
  const [prospectSearchQuery, setProspectSearchQuery] = useState('');
  const fileInputRef = useRef(null);
  const prospectSectionRef = useRef(null);
  const navigate = useNavigate();

  // Filter prospects based on search query
  const filteredProspects = activeProspects.filter((p) =>
    p.instagram_username?.toLowerCase().includes(prospectSearchQuery.toLowerCase())
  );

  // Fetch active prospects when stage > 0 (follow-up)
  useEffect(() => {
    if (selectedStage > 0) {
      fetchActiveProspects();
    } else {
      setActiveProspects([]);
      setSelectedProspect('');
      setOcrFailed(false);
    }
  }, [selectedStage]);

  const fetchActiveProspects = async () => {
    try {
      const response = await api.get('/messages/active-prospects');
      if (response.data.success) {
        setActiveProspects(response.data.data);
      }
    } catch (err) {
      console.error('Failed to fetch active prospects:', err);
    }
  };

  const stages = [
    { value: 0, label: 'Canvassing (Day 0)' },
    { value: 1, label: 'Follow Up 1 (Day 1)' },
    { value: 2, label: 'Follow Up 2 (Day 2)' },
    { value: 3, label: 'Follow Up 3 (Day 3)' },
    { value: 4, label: 'Follow Up 4 (Day 4)' },
    { value: 5, label: 'Follow Up 5 (Day 5)' },
    { value: 6, label: 'Follow Up 6 (Day 6)' },
    { value: 7, label: 'Follow Up 7 (Day 7)' },
  ];

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile) {
      setFile(selectedFile);
      setMessage({ type: '', text: '' });
      setErrors([]);

      // Create preview
      const reader = new FileReader();
      reader.onloadend = () => {
        setPreview(reader.result);
      };
      reader.readAsDataURL(selectedFile);
    }
  };

  // Build FormData for upload (reused by submit and retry)
  const buildFormData = () => {
    const formData = new FormData();
    formData.append('screenshot', file);
    formData.append('stage', selectedStage.toString());
    formData.append('category', selectedCategory);
    if (contact) formData.append('contact_number', contact);
    if (instagramLink) formData.append('instagram_link', instagramLink);
    if (channel) formData.append('channel', channel);
    if (interactionStatus) formData.append('interaction_status', interactionStatus);
    if (lokasi) formData.append('lokasi', lokasi);
    if (selectedProspect) formData.append('prospect_id', selectedProspect);
    return formData;
  };

  // Friendly error message mapping
  const getFriendlyError = (errorData) => {
    const msg = errorData?.message || '';

    if (msg.includes('Gagal mendeteksi username')) {
      return '⚠️ Sistem tidak bisa membaca username dari screenshot. Silakan pilih prospect manual dari dropdown di atas, lalu tekan "Coba Upload Ulang".';
    }
    if (msg.includes('Pesan tidak sesuai dengan template')) {
      return '⚠️ Pesan di screenshot tidak sesuai dengan template yang diharapkan. Pastikan screenshot yang diupload sesuai dengan stage Follow Up yang dipilih.';
    }
    if (msg.includes('Prospect tidak ditemukan')) {
      return '⚠️ Prospect belum pernah di-canvassing sebelumnya. Pastikan sudah upload Canvassing (Day 0) untuk prospect ini terlebih dahulu.';
    }
    if (msg.includes('Stage sebelumnya belum ada')) {
      return `⚠️ Anda belum upload Follow Up ${selectedStage - 1} untuk prospect ini. Upload stage sebelumnya dulu ya.`;
    }
    if (msg.includes('Siklus canvassing') && msg.includes('tidak aktif')) {
      return '⚠️ Siklus canvassing prospect ini sudah tidak aktif (mungkin ditolak atau selesai). Hubungi supervisor untuk informasi lebih lanjut.';
    }
    if (msg.includes('sudah pernah diupload')) {
      return '⚠️ Screenshot ini sudah pernah diupload sebelumnya. Gunakan screenshot yang berbeda.';
    }
    if (msg.includes('Terjadi kesalahan sistem')) {
      return '❌ Terjadi kesalahan di server. Coba beberapa saat lagi, atau hubungi supervisor.';
    }
    return msg || 'Upload gagal. Silakan coba lagi.';
  };

  // Perform the upload request
  const doUpload = async (formData) => {
    setUploading(true);
    setMessage({ type: '', text: '' });
    setErrors([]);

    try {
      const response = await api.post('/messages/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 60000, // 60s timeout for OCR processing
      });

      if (!response.data.success) {
        setMessage({ type: 'error', text: response.data.message || 'Upload gagal' });
        setUploading(false);
        return;
      }

      setMessage({ type: 'success', text: '✅ Screenshot berhasil diupload!' });
      setOcrFailed(false);

      // Reset form
      setFile(null);
      setPreview(null);
      setSelectedStage(0);
      setSelectedCategory('');
      setContact('');
      setInstagramLink('');
      setChannel('');
      setInteractionStatus('');
      setLokasi('');
      setSelectedProspect('');
      setOcrFailed(false);
      if (fileInputRef.current) fileInputRef.current.value = '';

      setTimeout(() => navigate('/dashboard'), 2000);
    } catch (error) {
      const errorData = error.response?.data;

      if (errorData?.errors) {
        // Validation errors (array)
        setErrors(Array.isArray(errorData.errors) ? errorData.errors : Object.values(errorData.errors).flat());
      } else {
        // Show manual selection if backend signals it OR if it's a follow-up OCR failure
        if ((errorData?.show_manual_selection || errorData?.message?.includes('username')) && selectedStage > 0) {
          setOcrFailed(true);
          // Scroll to prospect selection section
          setTimeout(() => {
            prospectSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 300);
        }
        setMessage({ type: 'error', text: getFriendlyError(errorData) });
      }
    } finally {
      setUploading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!file) {
      setMessage({ type: 'error', text: '📷 Pilih screenshot terlebih dahulu' });
      return;
    }
    if (selectedStage === null || selectedStage === undefined) {
      setMessage({ type: 'error', text: 'Pilih stage terlebih dahulu' });
      return;
    }
    if (!selectedCategory) {
      setMessage({ type: 'error', text: 'Pilih kategori terlebih dahulu' });
      return;
    }

    await doUpload(buildFormData());
  };

  // Retry with manual prospect selected
  const handleRetry = async () => {
    if (!file) {
      setMessage({ type: 'error', text: '📷 File sudah tidak tersedia. Silakan pilih file lagi.' });
      return;
    }
    await doUpload(buildFormData());
  };

  return (
    <div className="max-w-4xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-6">Upload Screenshot DM</h1>

      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-md p-6">
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Pilih Stage *
          </label>
          <select
            value={selectedStage}
            onChange={(e) => setSelectedStage(parseInt(e.target.value))}
            className="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            required
          >
            {stages.map((stage) => (
              <option key={stage.value} value={stage.value}>
                {stage.label}
              </option>
            ))}
          </select>
          <p className="mt-2 text-sm text-gray-500">
            {selectedStage === 0
              ? 'Upload screenshot untuk canvassing awal'
              : `Pastikan sudah upload Follow Up ${selectedStage - 1} sebelumnya`}
          </p>
        </div>

        {/* Manual Prospect Selection for Follow-up (ALWAYS shown when stage > 0) */}
        {selectedStage > 0 && (
          <div
            ref={prospectSectionRef}
            className={`mb-6 p-4 rounded-lg transition-all duration-300 ${
              ocrFailed
                ? 'bg-yellow-50 border-2 border-yellow-400 shadow-md'
                : 'bg-blue-50 border border-blue-200'
            }`}
          >
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {ocrFailed
                ? '⚠️ OCR Gagal - Pilih Prospect Manual *'
                : '📋 Pilih Prospect untuk Follow Up (Disarankan)'}
            </label>
            <p className="text-xs text-gray-500 mb-3">
              {ocrFailed
                ? 'Sistem tidak bisa membaca username dari screenshot. Pilih prospect dari daftar di bawah, lalu klik "Coba Upload Ulang".'
                : 'Pilih prospect agar upload lebih akurat. Jika tidak dipilih, sistem akan coba deteksi otomatis dari screenshot.'}
            </p>

            {activeProspects.length > 0 ? (
              <>
                {/* Search Input */}
                <div className="relative mb-2">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </div>
                  <input
                    type="text"
                    value={prospectSearchQuery}
                    onChange={(e) => setProspectSearchQuery(e.target.value)}
                    placeholder="Cari username prospect..."
                    className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                  />
                  {prospectSearchQuery && (
                    <button
                      type="button"
                      onClick={() => setProspectSearchQuery('')}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                    >
                      <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  )}
                </div>

                {/* Filtered Dropdown */}
                <select
                  value={selectedProspect}
                  onChange={(e) => setSelectedProspect(e.target.value)}
                  className={`block w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 ${
                    ocrFailed ? 'border-yellow-400 bg-yellow-50 ring-2 ring-yellow-300' : 'border-gray-300'
                  }`}
                >
                  <option value="">-- Pilih prospect (disarankan) --</option>
                  {filteredProspects.length > 0 ? (
                    filteredProspects.map((p) => (
                      <option key={p.id} value={p.id}>
                        @{p.instagram_username} (Stage {p.current_stage})
                      </option>
                    ))
                  ) : prospectSearchQuery ? (
                    <option disabled>Tidak ditemukan</option>
                  ) : null}
                </select>

                <p className="mt-2 text-xs text-gray-500">
                  Total: {activeProspects.length} prospect aktif
                  {prospectSearchQuery && filteredProspects.length !== activeProspects.length &&
                    ` • ${filteredProspects.length} hasil pencarian`}
                </p>
              </>
            ) : (
              <p className="text-sm text-gray-500 italic">
                Belum ada prospect aktif. Pastikan sudah upload Canvassing (Day 0) terlebih dahulu.
              </p>
            )}
          </div>
        )}

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Kategori *
          </label>
          <select
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value)}
            className="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            required
          >
            <option value="">Pilih Kategori</option>
            <option value="umkm_fb">UMKM F&B</option>
            <option value="coffee_shop">Coffee Shop</option>
            <option value="restoran">Restoran</option>
          </select>
        </div>

        <div className="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Kontak (WA/HP)
            </label>
            <input
              type="text"
              value={contact}
              onChange={(e) => setContact(e.target.value)}
              placeholder="Contoh: 08123456789"
              className="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Link Instagram (Opsional)
            </label>
            <input
              type="url"
              value={instagramLink}
              onChange={(e) => setInstagramLink(e.target.value)}
              placeholder="https://instagram.com/username"
              className="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Channel FU
            </label>
            <select
              value={channel}
              onChange={(e) => setChannel(e.target.value)}
              className="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            >
              <option value="">Pilih Channel</option>
              <option value="instagram">Instagram</option>
              <option value="tiktok">TikTok</option>
              <option value="facebook">Facebook</option>
              <option value="threads">Threads</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="other">Lainnya</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Lokasi (Opsional)
            </label>
            <input
              type="text"
              value={lokasi}
              onChange={(e) => setLokasi(e.target.value)}
              placeholder="Contoh: Jakarta Selatan"
              className="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Status Interaksi
          </label>
          <select
            value={interactionStatus}
            onChange={(e) => setInteractionStatus(e.target.value)}
            className="block w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
          >
            <option value="">Pilih Status Interaksi (Opsional)</option>
            <option value="no_response">No Response (Tidak ada Balasan)</option>
            <option value="menolak">Menolak</option>
            <option value="tertarik">Tertarik</option>
            <option value="menerima">Menerima (Closing)</option>
          </select>
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Pilih Screenshot *
          </label>
          <input
            ref={fileInputRef}
            type="file"
            onChange={handleFileChange}
            className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
            required
          />
          <p className="mt-2 text-sm text-gray-500">
            Format: JPG, PNG, GIF (Maks 10MB)
          </p>
        </div>

        {preview && (
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Preview
            </label>
            <img
              src={preview}
              alt="Preview"
              className="max-w-full h-auto rounded-lg border border-gray-300"
            />
          </div>
        )}

        {message.text && (
          <div
            className={`mb-4 p-4 rounded-lg ${message.type === 'success'
              ? 'bg-green-50 text-green-800 border border-green-200'
              : 'bg-red-50 text-red-800 border border-red-200'
              }`}
          >
            <p className="whitespace-pre-line">{message.text}</p>
          </div>
        )}

        {errors.length > 0 && (
          <div className="mb-4 p-4 bg-red-50 text-red-800 border border-red-200 rounded-lg">
            <p className="font-medium mb-1">❌ Upload gagal:</p>
            <ul className="list-disc list-inside text-sm">
              {errors.map((error, index) => (
                <li key={index}>{error}</li>
              ))}
            </ul>
          </div>
        )}

        <div className="space-y-3">
          <button
            type="submit"
            disabled={uploading || !file}
            className="w-full bg-indigo-600 text-white py-2.5 px-4 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-colors"
          >
            {uploading ? (
              <span className="flex items-center justify-center gap-2">
                <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Mengupload & Memproses OCR...
              </span>
            ) : 'Upload Screenshot'}
          </button>

          {/* Retry button - shown after OCR failure when prospect can be selected */}
          {ocrFailed && selectedStage > 0 && file && (
            <button
              type="button"
              onClick={handleRetry}
              disabled={uploading || !selectedProspect}
              className="w-full bg-yellow-500 text-white py-2.5 px-4 rounded-md hover:bg-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-colors"
            >
              {!selectedProspect
                ? '↑ Pilih prospect dulu dari dropdown di atas'
                : '🔄 Coba Upload Ulang (dengan prospect manual)'}
            </button>
          )}
        </div>
      </form>
    </div>
  );
}
