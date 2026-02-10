import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { compressImage } from '../utils/imageCompressor';
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
  const [showManualSelection, setShowManualSelection] = useState(false);
  const [prospectSearchQuery, setProspectSearchQuery] = useState('');
  const [manualUsername, setManualUsername] = useState('');
  const fileInputRef = useRef(null);
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
      setShowManualSelection(false);
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

  const handleFileChange = async (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile) {
      try {
        setFile(null); // Reset file first
        const originalSize = (selectedFile.size / 1024).toFixed(0);

        // Compress if > 500KB (Seems server/proxy has strict limit around 1MB or less)
        // 720KB failed, 100KB works. So we target < 300KB result.
        if (selectedFile.size > 500 * 1024) {
             setMessage({ type: 'info', text: '📷 Sedang memproses & kompres gambar...' });
             
             // Compress with retry (mobile Canvas can fail intermittently)
             let compressed = null;
             let attempt = 0;
             const maxAttempts = 3;
             
             while (attempt < maxAttempts) {
               attempt++;
               setMessage({ type: 'info', text: `📷 Memproses gambar... ${attempt > 1 ? `(percobaan ${attempt})` : ''}` });
               compressed = await compressImage(selectedFile);
               
               // If result is > 150KB, compression succeeded (enough quality for OCR)
               if (compressed.size > 150 * 1024) break;
             }

             const compressedSize = (compressed.size / 1024).toFixed(0);
             
              if (compressed.size < 150 * 1024) {
                  // All retries failed, warn user
                  setFile(selectedFile);
                  setMessage({ type: 'warning', text: `⚠️ Kompresi gagal ${maxAttempts}x. Coba tutup app lain dulu, lalu pilih ulang gambar.` });
                  const reader = new FileReader();
                  reader.onloadend = () => setPreview(reader.result);
                  reader.readAsDataURL(selectedFile);
             } else {
                  setFile(compressed);
                  setMessage({ type: 'success', text: `✅ Siap upload! [${selectedFile.type}] (${originalSize}KB ➡️ ${compressedSize}KB)` });
                  const reader = new FileReader();
                  reader.onloadend = () => setPreview(reader.result);
                  reader.readAsDataURL(compressed);
             }
        } else {
             // Use original file if very small (< 500KB)
             setFile(selectedFile);
             setMessage({ type: 'success', text: `✅ Siap upload! (Size: ${originalSize}KB)` });
             setErrors([]);
             
             // Preview original
             const reader = new FileReader();
             reader.onloadend = () => setPreview(reader.result);
             reader.readAsDataURL(selectedFile);
        }
      } catch (error) {
        console.error('Compression failed:', error);
        // Fallback to original file
        setFile(selectedFile);
        const reader = new FileReader();
        reader.onloadend = () => {
          setPreview(reader.result);
        };
        reader.readAsDataURL(selectedFile);
      }
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!file) {
      setMessage({ type: 'error', text: 'Pilih screenshot terlebih dahulu' });
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

    setUploading(true);
    setMessage({ type: '', text: '' });
    setErrors([]);

    try {
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
      if (manualUsername) formData.append('manual_username', manualUsername);

      const response = await api.post('/messages/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      // Check if backend returned success: false (even with 200 status)
      if (!response.data.success) {
        setMessage({
          type: 'error',
          text: response.data.message || 'Upload gagal',
        });
        setUploading(false);
        return;
      }

      setMessage({
        type: 'success',
        text: 'Screenshot berhasil diupload!',
      });

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
      setShowManualSelection(false);
      setManualUsername('');
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }

      // Redirect to dashboard after 2 seconds
      setTimeout(() => {
        navigate('/dashboard');
      }, 2000);
    } catch (error) {
      const errorData = error.response?.data;
      if (errorData?.errors) {
        setErrors(errorData.errors);
      } else {
        // Check if backend signals to show manual selection (all stages)
        if (errorData?.show_manual_selection) {
          setShowManualSelection(true);
        }
        setMessage({
          type: 'error',
          text: errorData?.message || 'Upload gagal. Silakan coba lagi.',
        });
      }
    } finally {
      setUploading(false);
    }
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

        {/* Manual Username Input for Day 0 (shown when OCR fails) */}
        {selectedStage === 0 && showManualSelection && (
          <div className="mb-6 p-4 rounded-lg bg-yellow-50 border-2 border-yellow-400">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              ⚠️ OCR Gagal - Ketik Username Instagram Manual *
            </label>
            <input
              type="text"
              value={manualUsername}
              onChange={(e) => setManualUsername(e.target.value.replace('@', ''))}
              placeholder="Contoh: tokokopimaru (tanpa @)"
              className="block w-full border border-yellow-400 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-yellow-500 focus:border-yellow-500"
            />
            <p className="mt-1 text-xs text-gray-500">Ketik username Instagram prospect (tanpa @), lalu klik Upload lagi.</p>
          </div>
        )}

        {/* Manual Prospect Selection for Follow-up (shown when stage > 0) */}
        {selectedStage > 0 && activeProspects.length > 0 && (
          <div className={`mb-6 p-4 rounded-lg ${showManualSelection ? 'bg-yellow-50 border-2 border-yellow-400' : 'bg-gray-50 border border-gray-200'}`}>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              {showManualSelection ? '⚠️ OCR Gagal - Pilih Prospect Manual *' : 'Pilih Prospect Manual (Opsional)'}
            </label>
            
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
                placeholder="Cari username..."
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
              className={`block w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 ${showManualSelection ? 'border-yellow-400 bg-yellow-50' : 'border-gray-300'}`}
            >
              <option value="">-- Biarkan kosong jika OCR berhasil --</option>
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
            
            <p className="mt-2 text-sm text-gray-500">
              {showManualSelection
                ? 'OCR tidak mendeteksi username. Silakan pilih prospect yang ingin di-follow up dari daftar di atas.'
                : `Ketik username untuk mencari. Total: ${activeProspects.length} prospect aktif.`}
            </p>
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
            className={`mb-4 p-4 rounded ${message.type === 'success'
              ? 'bg-green-50 text-green-800 border border-green-200'
              : 'bg-red-50 text-red-800 border border-red-200'
              }`}
          >
            {message.text}
          </div>
        )}

        {errors.length > 0 && (
          <div className="mb-4 p-4 bg-red-50 text-red-800 border border-red-200 rounded">
            <ul className="list-disc list-inside">
              {errors.map((error, index) => (
                <li key={index}>{error}</li>
              ))}
            </ul>
          </div>
        )}

        <button
          type="submit"
          disabled={uploading || !file}
          className="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {uploading ? 'Mengupload...' : 'Upload Screenshot'}
        </button>
      </form>
    </div>
  );
}
