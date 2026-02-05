import { useState, useEffect, useRef } from 'react';
import api from '../api';
import { compressImage } from '../utils/imageUtils';
import { useToast } from '../context/ToastContext';

export default function OnlineCanvassing() {
  const { showToast } = useToast();
  const [reports, setReports] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    business_name: '',
    contact_name: '',
    contact_number: '',
    status: 'on_progress',
    notes: '',
    rejection_reason: '',
    photo: null,
  });
  const [isEditing, setIsEditing] = useState(false);
  const [currentId, setCurrentId] = useState(null);
  const [photoPreview, setPhotoPreview] = useState(null);
  const [selectedImage, setSelectedImage] = useState(null);
  const fileInputRef = useRef(null);

  useEffect(() => {
    fetchReports();
  }, []);

  const fetchReports = async () => {
    try {
      setLoading(true);
      const res = await api.get('/online-canvassing?per_page=50');
      setReports(res.data.data.data || []);
    } catch (error) {
      console.error('Error fetching reports:', error);
      showToast('Gagal memuat laporan: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handlePhotoChange = async (e) => {
    const file = e.target.files[0];
    if (file) {
      try {
        // Compress image if it's too large (max 1.5MB)
        const compressedFile = await compressImage(file, 1.5);
        setFormData((prev) => ({ ...prev, photo: compressedFile }));
        setPhotoPreview(URL.createObjectURL(compressedFile));
      } catch (error) {
        console.error('Failed to compress image:', error);
        // Fallback to original file if compression fails
        setFormData((prev) => ({ ...prev, photo: file }));
        setPhotoPreview(URL.createObjectURL(file));
      }
    }
  };

  const resetForm = () => {
    setFormData({
      business_name: '',
      contact_name: '',
      contact_number: '',
      status: 'on_progress',
      notes: '',
      rejection_reason: '',
      photo: null,
    });
    setPhotoPreview(null);
    setIsEditing(false);
    setCurrentId(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleEdit = (report) => {
    setFormData({
      business_name: report.business_name,
      contact_name: report.contact_name || '',
      contact_number: report.contact_number || '',
      status: report.status,
      notes: report.notes || '',
      rejection_reason: report.rejection_reason || '',
      photo: null,
    });
    setPhotoPreview(report.photo_url);
    setIsEditing(true);
    setCurrentId(report.id);
    setShowForm(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.business_name) {
      showToast('Nama usaha harus diisi', 'warning');
      return;
    }

    try {
      setSubmitting(true);
      
      const data = new FormData();
      data.append('business_name', formData.business_name);
      if (formData.contact_name) data.append('contact_name', formData.contact_name);
      if (formData.contact_number) data.append('contact_number', formData.contact_number);
      data.append('status', formData.status);
      if (formData.status === 'rejected' && formData.rejection_reason) {
        data.append('rejection_reason', formData.rejection_reason);
      }
      data.append('visit_date', new Date().toISOString().split('T')[0]);
      
      if (formData.photo) data.append('photo', formData.photo);
      if (formData.notes) data.append('notes', formData.notes);

      if (isEditing) {
        // For PUT/PATCH with FormData in Laravel/PHP, we need _method field or use POST with _method
        data.append('_method', 'PUT');
        await api.post(`/online-canvassing/${currentId}`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
      } else {
        await api.post('/online-canvassing', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
      }

      showToast(isEditing ? 'Laporan berhasil diperbarui' : 'Laporan berhasil dikirim', 'success');
      resetForm();
      setShowForm(false);
      fetchReports();
    } catch (error) {
      showToast('Gagal menyimpan: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-6xl mx-auto p-4 md:p-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 md:mb-6">
        <div>
          <h1 className="text-xl md:text-2xl font-bold">Laporan Canvassing Offline</h1>
          <p className="text-gray-500 text-sm">Buat laporan kunjungan langsung (tanpa group)</p>
        </div>
        <button
          onClick={() => setShowForm(true)}
          className="w-full sm:w-auto bg-indigo-600 text-white px-4 py-2 md:px-5 md:py-2.5 rounded-lg font-semibold hover:bg-indigo-700 flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition-all duration-200 text-sm md:text-base"
        >
          <svg className="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
          Buat Laporan Baru
        </button>
      </div>

      {/* Reports List */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business Info</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {reports.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-6 py-12 text-center text-gray-500">
                    Belum ada laporan online canvassing
                  </td>
                </tr>
              ) : (
                reports.map((report) => (
                  <tr key={report.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {new Date(report.visit_date).toLocaleDateString()}
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm font-medium text-gray-900">{report.business_name}</div>
                      {report.notes && <div className="text-sm text-gray-500 truncate max-w-xs">{report.notes}</div>}
                    </td>
                    <td className="px-6 py-4">
                      {report.contact_name && <div className="text-sm font-medium text-gray-900">{report.contact_name}</div>}
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
                        <div className="text-xs text-red-600 mt-1 max-w-xs truncate">
                          {report.rejection_reason}
                        </div>
                      )}
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
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <button
                        onClick={() => handleEdit(report)}
                        className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                      >
                        <svg className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal Form */}
      {showForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg w-full max-w-md max-h-[90vh] overflow-y-auto">
            <div className="p-6">
              <h2 className="text-lg font-semibold mb-4">
                {isEditing ? 'Edit Laporan' : 'Buat Laporan Baru'}
              </h2>
              
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
                    placeholder="Nama toko/brand..."
                  />
                </div>

                {/* Contact Name */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nama PIC
                  </label>
                  <input
                    type="text"
                    name="contact_name"
                    value={formData.contact_name}
                    onChange={handleChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="Contoh: Pak Budi"
                  />
                </div>

                {/* Contact Number */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Nomor Kontak
                  </label>
                  <input
                    type="text"
                    name="contact_number"
                    value={formData.contact_number}
                    onChange={handleChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                    placeholder="081xxx"
                  />
                </div>

                {/* Status */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Status <span className="text-red-500">*</span>
                  </label>
                  <select
                    name="status"
                    value={formData.status}
                    onChange={handleChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                  >
                    <option value="on_progress">On Progress</option>
                    <option value="registered">Registered</option>
                    <option value="rejected">Rejected</option>
                  </select>
                </div>

                {/* Rejection Reason - Conditional */}
                {formData.status === 'rejected' && (
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Alasan Penolakan <span className="text-red-500">*</span>
                    </label>
                    <textarea
                      name="rejection_reason"
                      value={formData.rejection_reason}
                      onChange={handleChange}
                      required
                      className="w-full border border-gray-300 rounded-md px-3 py-2"
                      placeholder="Kenapa ditolak?"
                    />
                  </div>
                )}

                {/* Proof Image */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Bukti (Screenshot/Foto)
                  </label>
                  <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handlePhotoChange}
                    className="w-full border border-gray-300 rounded-md px-3 py-2"
                  />
                  {photoPreview && (
                    <img
                      src={photoPreview}
                      alt="Preview"
                      className="mt-2 w-full h-48 object-cover rounded-lg"
                      onClick={() => setSelectedImage(photoPreview)} 
                    />
                  )}
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
                <div className="flex gap-4 pt-4 border-t border-gray-100 mt-6">
                  <button
                    type="button"
                    onClick={() => {
                      setShowForm(false);
                      resetForm();
                    }}
                    className="flex-1 py-2.5 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 font-medium transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={submitting}
                    className="flex-1 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium shadow-sm hover:shadow transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    {submitting ? (
                      <span className="flex items-center justify-center gap-2">
                        <svg className="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Menyimpan...
                      </span>
                    ) : (
                      'Simpan Laporan'
                    )}
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
