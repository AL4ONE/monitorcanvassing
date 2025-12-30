import { useState, useRef } from 'react';
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
  const [channel, setChannel] = useState('');
  const [interactionStatus, setInteractionStatus] = useState('');
  const fileInputRef = useRef(null);
  const navigate = useNavigate();

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
      if (channel) formData.append('channel', channel);
      if (interactionStatus) formData.append('interaction_status', interactionStatus);

      const response = await api.post('/messages/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

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
      setChannel('');
      setInteractionStatus('');
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
            accept="image/*"
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
