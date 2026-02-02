import { useState, useEffect, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../api';

export default function CanvassingExecution() {
  const { groupId } = useParams();
  const [group, setGroup] = useState(null);
  const [todayStats, setTodayStats] = useState(null);
  const [prospects, setProspects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    business_name: '',
    photo: null,
    contact_name: '',
    contact_number: '',
    status: 'on_progress',
    notes: '',
  });
  const [photoPreview, setPhotoPreview] = useState(null);
  const [selectedImage, setSelectedImage] = useState(null);
  const fileInputRef = useRef(null);

  useEffect(() => {
    fetchData();
  }, [groupId]);

  const fetchData = async () => {
    try {
      setLoading(true);
      const [groupRes, statsRes, prospectsRes] = await Promise.all([
        api.get(`/canvassing-groups/${groupId}`),
        api.get(`/canvassing-groups/${groupId}/today-stats`),
        api.get(`/canvassing-groups/${groupId}/prospects`, { 
          params: { date: new Date().toISOString().split('T')[0] }
        }),
      ]);
      
      setGroup(groupRes.data.data);
      setTodayStats(statsRes.data.data);
      setProspects(prospectsRes.data.data?.data || []);
    } catch (error) {
      console.error('Error fetching data:', error);
      alert('Gagal memuat data: ' + (error.response?.data?.message || error.message));
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handlePhotoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setFormData((prev) => ({ ...prev, photo: file }));
      setPhotoPreview(URL.createObjectURL(file));
    }
  };

  const resetForm = () => {
    setFormData({
      business_name: '',
      photo: null,
      contact_name: '',
      contact_number: '',
      status: 'on_progress',
      notes: '',
    });
    setPhotoPreview(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.business_name) {
      alert('Nama usaha harus diisi');
      return;
    }

    try {
      setSubmitting(true);
      
      const data = new FormData();
      data.append('business_name', formData.business_name);
      data.append('status', formData.status);
      data.append('visit_date', new Date().toISOString().split('T')[0]);
      
      if (formData.photo) data.append('photo', formData.photo);
      if (formData.contact_name) data.append('contact_name', formData.contact_name);
      if (formData.contact_number) data.append('contact_number', formData.contact_number);
      if (formData.notes) data.append('notes', formData.notes);

      await api.post(`/canvassing-groups/${groupId}/prospects`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      alert('Prospect berhasil ditambahkan');
      resetForm();
      setShowForm(false);
      fetchData();
    } catch (error) {
      alert('Gagal menyimpan: ' + (error.response?.data?.message || error.message));
    } finally {
      setSubmitting(false);
    }
  };

  const handleUpdateStatus = async (prospectId, newStatus) => {
    try {
      await api.patch(`/prospects/${prospectId}/status`, { status: newStatus });
      fetchData();
    } catch (error) {
      alert('Gagal update status: ' + (error.response?.data?.message || error.message));
    }
  };

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-4xl mx-auto p-6">
      {/* Header */}
      <div className="mb-6">
        <Link to="/my-canvassing-groups" className="text-indigo-600 hover:text-indigo-800 mb-2 inline-block">
          ← Kembali
        </Link>
        <h1 className="text-2xl font-bold">{group?.name}</h1>
        <p className="text-gray-500">{group?.city}{group?.district ? `, ${group?.district}` : ''}</p>
      </div>

      {/* Today's Progress */}
      <div className="bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg p-6 mb-6">
        <h2 className="text-lg font-semibold mb-4">Progress Hari Ini</h2>
        <div className="grid grid-cols-4 gap-4 text-center">
          <div>
            <p className="text-3xl font-bold">{todayStats?.target || 0}</p>
            <p className="text-indigo-200 text-sm">Target</p>
          </div>
          <div>
            <p className="text-3xl font-bold">{todayStats?.total_visits || 0}</p>
            <p className="text-indigo-200 text-sm">Total Visit</p>
          </div>
          <div>
            <p className="text-3xl font-bold text-green-300">{todayStats?.registered || 0}</p>
            <p className="text-indigo-200 text-sm">Registered</p>
          </div>
          <div>
            <p className="text-3xl font-bold text-yellow-300">{todayStats?.remaining || 0}</p>
            <p className="text-indigo-200 text-sm">Sisa Target</p>
          </div>
        </div>
        
        {/* Progress Bar */}
        <div className="mt-4">
          <div className="flex justify-between text-sm mb-1">
            <span>Progress</span>
            <span>{Math.round(((todayStats?.registered || 0) / (todayStats?.target || 1)) * 100)}%</span>
          </div>
          <div className="w-full bg-indigo-400 rounded-full h-3">
            <div
              className="bg-white h-3 rounded-full transition-all"
              style={{ width: `${Math.min(100, ((todayStats?.registered || 0) / (todayStats?.target || 1)) * 100)}%` }}
            ></div>
          </div>
        </div>
      </div>

      {/* Add Prospect Button */}
      <button
        onClick={() => setShowForm(true)}
        className="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 mb-6 flex items-center justify-center gap-2"
      >
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </svg>
        Tambah Prospect Baru
      </button>

      {/* Today's Prospects */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h2 className="text-lg font-semibold mb-4">Input Hari Ini ({prospects.length})</h2>
        
        {prospects.length === 0 ? (
          <p className="text-center text-gray-500 py-4">Belum ada input hari ini</p>
        ) : (
          <div className="space-y-4">
            {prospects.map((prospect) => (
              <div key={prospect.id} className="border rounded-lg p-4 flex gap-4">
                {/* Photo */}
                {prospect.photo_url ? (
                  <img
                    src={prospect.photo_url}
                    alt={prospect.business_name}
                    className="w-20 h-20 object-cover rounded-lg cursor-pointer hover:opacity-80 transition-opacity"
                    onClick={() => setSelectedImage(prospect.photo_url)}
                  />
                ) : (
                  <div className="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </div>
                )}

                {/* Info */}
                <div className="flex-1">
                  <h3 className="font-semibold">{prospect.business_name}</h3>
                  {prospect.contact_name && (
                    <p className="text-sm text-gray-500">PIC: {prospect.contact_name}</p>
                  )}
                  {prospect.contact_number && (
                    <p className="text-sm text-gray-500">Kontak: {prospect.contact_number}</p>
                  )}
                  {prospect.notes && (
                    <p className="text-sm text-gray-400 mt-1">{prospect.notes}</p>
                  )}
                </div>

                {/* Status */}
                <div className="flex flex-col items-end gap-2">
                  <span className={`px-3 py-1 text-xs rounded-full ${
                    prospect.status === 'registered' 
                      ? 'bg-green-100 text-green-800' 
                      : 'bg-yellow-100 text-yellow-800'
                  }`}>
                    {prospect.status === 'registered' ? 'Registered' : 'On Progress'}
                  </span>
                  
                  {prospect.status === 'on_progress' && (
                    <button
                      onClick={() => handleUpdateStatus(prospect.id, 'registered')}
                      className="text-xs text-green-600 hover:text-green-800"
                    >
                      Set Registered
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Add Prospect Modal */}
      {showForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div className="p-6">
              <h2 className="text-lg font-semibold mb-4">Tambah Prospect Baru</h2>
              
              <form onSubmit={handleSubmit} className="space-y-4">
                {/* Business Name */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nama Usaha <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    name="business_name"
                    value={formData.business_name}
                    onChange={handleChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="Contoh: Warung Makan Barokah"
                  />
                </div>

                {/* Photo */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Foto
                  </label>
                  <input
                    type="file"
                    ref={fileInputRef}
                    accept="image/*"
                    capture="environment"
                    onChange={handlePhotoChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                  />
                  {photoPreview && (
                    <img
                      src={photoPreview}
                      alt="Preview"
                      className="mt-2 w-full h-48 object-cover rounded-lg"
                    />
                  )}
                </div>

                {/* Contact Name */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nama PIC/Contact
                  </label>
                  <input
                    type="text"
                    name="contact_name"
                    value={formData.contact_name}
                    onChange={handleChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="Contoh: Pak Ahmad"
                  />
                </div>

                {/* Contact Number */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nomor Kontak
                  </label>
                  <input
                    type="tel"
                    name="contact_number"
                    value={formData.contact_number}
                    onChange={handleChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="Contoh: 081234567890"
                  />
                </div>

                {/* Status */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Status <span className="text-red-500">*</span>
                  </label>
                  <div className="flex gap-4">
                    <label className="flex items-center gap-2">
                      <input
                        type="radio"
                        name="status"
                        value="on_progress"
                        checked={formData.status === 'on_progress'}
                        onChange={handleChange}
                      />
                      <span>On Progress</span>
                    </label>
                    <label className="flex items-center gap-2">
                      <input
                        type="radio"
                        name="status"
                        value="registered"
                        checked={formData.status === 'registered'}
                        onChange={handleChange}
                      />
                      <span>Registered</span>
                    </label>
                  </div>
                </div>

                {/* Notes */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Catatan
                  </label>
                  <textarea
                    name="notes"
                    value={formData.notes}
                    onChange={handleChange}
                    rows={3}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="Catatan tambahan..."
                  />
                </div>

                {/* Actions */}
                <div className="flex gap-4 pt-4">
                  <button
                    type="button"
                    onClick={() => {
                      setShowForm(false);
                      resetForm();
                    }}
                    className="flex-1 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={submitting}
                    className="flex-1 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                  >
                    {submitting ? 'Menyimpan...' : 'Simpan'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

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
