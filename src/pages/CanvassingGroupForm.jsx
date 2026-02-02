import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../api';

export default function CanvassingGroupForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEdit = Boolean(id);

  const [formData, setFormData] = useState({
    name: '',
    category: '',
    target_per_day: 5,
    start_date: '',
    end_date: '',
    city: '',
    district: '',
    status: 'open',
    cancel_reason: '',
  });
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (isEdit) {
      fetchGroup();
    }
  }, [id]);

  const fetchGroup = async () => {
    try {
      setLoading(true);
      const response = await api.get(`/canvassing-groups/${id}`);
      const group = response.data.data;
      setFormData({
        name: group.name || '',
        category: group.category || '',
        target_per_day: group.target_per_day || 5,
        start_date: group.start_date?.split('T')[0] || '',
        end_date: group.end_date?.split('T')[0] || '',
        city: group.city || '',
        district: group.district || '',
        status: group.status || 'open',
        cancel_reason: group.cancel_reason || '',
      });
    } catch (error) {
      alert('Gagal memuat data: ' + (error.response?.data?.message || error.message));
      navigate('/canvassing-groups');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.name || !formData.category || !formData.start_date || !formData.end_date || !formData.city) {
      alert('Mohon isi semua field yang wajib');
      return;
    }

    try {
      setSaving(true);
      
      if (isEdit) {
        await api.put(`/canvassing-groups/${id}`, formData);
        alert('Group berhasil diupdate');
      } else {
        await api.post('/canvassing-groups', formData);
        alert('Group berhasil dibuat');
      }
      
      navigate('/canvassing-groups');
    } catch (error) {
      alert('Gagal menyimpan: ' + (error.response?.data?.message || error.message));
    } finally {
      setSaving(false);
    }
  };

  // Calculate total target
  const calculateTotalTarget = () => {
    if (!formData.start_date || !formData.end_date) return 0;
    const start = new Date(formData.start_date);
    const end = new Date(formData.end_date);
    const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    return days * formData.target_per_day;
  };

  if (loading) {
    return <div className="p-6">Memuat...</div>;
  }

  return (
    <div className="max-w-3xl mx-auto p-6">
      <h1 className="text-2xl font-bold mb-6">
        {isEdit ? 'Edit Canvassing Group' : 'Buat Canvassing Group Baru'}
      </h1>

      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-md p-6 space-y-6">
        {/* Name */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Nama Group <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            name="name"
            value={formData.name}
            onChange={handleChange}
            className="w-full border border-gray-300 rounded-md px-3 py-2"
            placeholder="Contoh: Canvassing UMKM Jakarta Pusat"
          />
        </div>

        {/* Category */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Kategori <span className="text-red-500">*</span>
          </label>
          <select
            name="category"
            value={formData.category}
            onChange={handleChange}
            className="w-full border border-gray-300 rounded-md px-3 py-2"
          >
            <option value="">Pilih Kategori</option>
            <option value="UMKM">UMKM</option>
            <option value="Restoran">Restoran</option>
            <option value="Coffee Shop">Coffee Shop</option>
            <option value="Retail">Retail</option>
            <option value="F&B">F&B</option>
            <option value="Lainnya">Lainnya</option>
          </select>
        </div>

        {/* Target per Day per Staff */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Target per Staff per Hari <span className="text-red-500">*</span>
          </label>
          <input
            type="number"
            name="target_per_day"
            value={formData.target_per_day}
            onChange={handleChange}
            min="1"
            className="w-full border border-gray-300 rounded-md px-3 py-2"
          />
          <p className="text-xs text-gray-500 mt-1">Jumlah merchant yang harus diinput oleh setiap staff per hari</p>
        </div>

        {/* Date Range */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Tanggal Mulai <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              name="start_date"
              value={formData.start_date}
              onChange={handleChange}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Tanggal Selesai <span className="text-red-500">*</span>
            </label>
            <input
              type="date"
              name="end_date"
              value={formData.end_date}
              onChange={handleChange}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
            />
          </div>
        </div>

        {/* Total Target Preview */}
        {formData.start_date && formData.end_date && (
          <div className="bg-indigo-50 border border-indigo-200 rounded-md p-4">
            <p className="text-sm text-indigo-800">
              <strong>Total Target:</strong> {calculateTotalTarget()} merchant 
              ({Math.ceil((new Date(formData.end_date) - new Date(formData.start_date)) / (1000 * 60 * 60 * 24)) + 1} hari × {formData.target_per_day} per hari)
            </p>
          </div>
        )}

        {/* Location */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Kota <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="city"
              value={formData.city}
              onChange={handleChange}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              placeholder="Contoh: Jakarta"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Kecamatan
            </label>
            <input
              type="text"
              name="district"
              value={formData.district}
              onChange={handleChange}
              className="w-full border border-gray-300 rounded-md px-3 py-2"
              placeholder="Contoh: Kemayoran"
            />
          </div>
        </div>

        {/* Status (only for edit) */}
        {isEdit && (
          <>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Status
              </label>
              <select
                name="status"
                value={formData.status}
                onChange={handleChange}
                className="w-full border border-gray-300 rounded-md px-3 py-2"
              >
                <option value="open">Open</option>
                <option value="on_progress">On Progress</option>
                <option value="closed">Closed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            {formData.status === 'cancelled' && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Alasan Pembatalan <span className="text-red-500">*</span>
                </label>
                <textarea
                  name="cancel_reason"
                  value={formData.cancel_reason}
                  onChange={handleChange}
                  rows={3}
                  className="w-full border border-gray-300 rounded-md px-3 py-2"
                  placeholder="Masukkan alasan pembatalan..."
                />
              </div>
            )}
          </>
        )}

        {/* Actions */}
        <div className="flex justify-end gap-4 pt-4 border-t">
          <button
            type="button"
            onClick={() => navigate('/canvassing-groups')}
            className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
          >
            Batal
          </button>
          <button
            type="submit"
            disabled={saving}
            className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
          >
            {saving ? 'Menyimpan...' : (isEdit ? 'Update' : 'Simpan')}
          </button>
        </div>
      </form>
    </div>
  );
}
