import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api from '../api';
import { useToast } from '../context/ToastContext';

export default function CanvassingGroupForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { showToast } = useToast();
  const isEdit = Boolean(id);

  const [formData, setFormData] = useState({
    name: '',
    category: '',
    target_per_day: 5,
    start_date: '',
    end_date: '',
    city: '',
    district: '',
    village: '',
    status: 'open',
    cancel_reason: '',
  });
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  // Location API States
  const [provinces, setProvinces] = useState([]);
  const [cities, setCities] = useState([]);
  const [districts, setDistricts] = useState([]);
  const [villages, setVillages] = useState([]);
  const [loadingLocation, setLoadingLocation] = useState(false);

  useEffect(() => {
    fetchProvinces();
    fetchCategories();
  }, []);

  // Category Logic
  const [categories, setCategories] = useState([]);
  const [loadingCategories, setLoadingCategories] = useState(false);
  const [showAddCategory, setShowAddCategory] = useState(false);
  const [newCategoryName, setNewCategoryName] = useState('');

  const fetchCategories = async () => {
    try {
      setLoadingCategories(true);
      const response = await api.get('/categories');
      setCategories(response.data.data);
    } catch (error) {
      console.error('Error fetching categories:', error);
    } finally {
      setLoadingCategories(false);
    }
  };

  const handleAddCategory = async () => {
    if (!newCategoryName.trim()) return;

    try {
      setLoadingCategories(true);
      const response = await api.post('/categories', { name: newCategoryName });
      await fetchCategories();
      setFormData(prev => ({ ...prev, category: response.data.data.name }));
      setNewCategoryName('');
      setShowAddCategory(false);
    } catch (error) {
      showToast('Gagal menambah kategori: ' + (error.response?.data?.message || error.message), 'error');
    } finally {
      setLoadingCategories(false);
    }
  };

  const fetchProvinces = async () => {
    try {
      setLoadingLocation(true);
      const response = await fetch('https://api-wilayah.stiqr.id/api/provinces.json');
      const data = await response.json();
      setProvinces(data);
    } catch (error) {
      console.error('Error fetching provinces:', error);
    } finally {
      setLoadingLocation(false);
    }
  };

  const handleProvinceChange = async (id) => {
    try {
      setLoadingLocation(true);
      setCities([]);
      setDistricts([]);
      setVillages([]);
      
      const response = await fetch(`https://api-wilayah.stiqr.id/api/regencies/${id}.json`);
      const data = await response.json();
      setCities(data);
    } catch (error) {
      console.error('Error fetching cities:', error);
    } finally {
      setLoadingLocation(false);
    }
  };

  const handleCityChange = async (id, name) => {
    // Update formData
    setFormData(prev => ({ ...prev, city: name, district: '', village: '' }));
    
    try {
      setLoadingLocation(true);
      setDistricts([]);
      setVillages([]);
      const response = await fetch(`https://api-wilayah.stiqr.id/api/districts/${id}.json`);
      const data = await response.json();
      setDistricts(data);
    } catch (error) {
      console.error('Error fetching districts:', error);
    } finally {
      setLoadingLocation(false);
    }
  };

  const handleDistrictChange = async (id, name) => {
    setFormData(prev => ({ ...prev, district: name, village: '' }));
    
    try {
      setLoadingLocation(true);
      setVillages([]);
      const response = await fetch(`https://api-wilayah.stiqr.id/api/villages/${id}.json`);
      const data = await response.json();
      setVillages(data);
    } catch (error) {
      console.error('Error fetching villages:', error);
    } finally {
      setLoadingLocation(false);
    }
  };

  const handleVillageChange = (id, name) => {
    setFormData(prev => ({ ...prev, village: name }));
  };

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
        village: group.village || '',
        status: group.status || 'open',
        cancel_reason: group.cancel_reason || '',
      });
    } catch (error) {
      showToast('Gagal memuat data: ' + (error.response?.data?.message || error.message), 'error');
      navigate('/canvassing-groups');
    } finally {
      setLoading(false);
    }
  };

  // Staff Assignment Logic (New Group Only)
  const [availableStaff, setAvailableStaff] = useState([]);
  const [selectedStaffIds, setSelectedStaffIds] = useState(new Set());
  const [loadingStaff, setLoadingStaff] = useState(false);

  useEffect(() => {
    if (!isEdit && formData.start_date && formData.end_date && formData.city) {
      fetchAvailableStaff();
    }
  }, [formData.start_date, formData.end_date, formData.city, formData.district]);

  const fetchAvailableStaff = async () => {
    try {
      setLoadingStaff(true);
      const params = {
        start_date: formData.start_date,
        end_date: formData.end_date,
        city: formData.city,
        district: formData.district,
        village: formData.village,
      };
      const response = await api.get('/canvassing-groups/check-available-staff', { params });
      setAvailableStaff(response.data.data || []);
    } catch (error) {
      console.error('Error fetching available staff:', error);
    } finally {
      setLoadingStaff(false);
    }
  };

  const handleStaffToggle = (staffId) => {
    setSelectedStaffIds(prev => {
      const next = new Set(prev);
      if (next.has(staffId)) {
        next.delete(staffId);
      } else {
        next.add(staffId);
      }
      return next;
    });
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!formData.name || !formData.category || !formData.start_date || !formData.end_date || !formData.city || !formData.district) {
      showToast('Mohon isi semua field yang wajib', 'warning');
      return;
    }

    try {
      setSaving(true);
      
      if (isEdit) {
        await api.put(`/canvassing-groups/${id}`, formData);
        showToast('Group berhasil diupdate', 'success');
      } else {
        const payload = { ...formData, staff_ids: Array.from(selectedStaffIds) };
        await api.post('/canvassing-groups', payload);
        showToast('Group berhasil dibuat', 'success');
      }
      
      navigate('/canvassing-groups');
    } catch (error) {
      showToast('Gagal menyimpan: ' + (error.response?.data?.message || error.message), 'error');
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
    <div className="max-w-3xl mx-auto p-4 md:p-6">
      <h1 className="text-xl md:text-2xl font-bold mb-4 md:mb-6">
        {isEdit ? 'Edit Canvassing Group' : 'Buat Canvassing Group Baru'}
      </h1>

      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-md p-4 md:p-6 space-y-4 md:space-y-6">
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
          
          <div className="flex gap-2">
            {!showAddCategory ? (
              <>
                <select
                  name="category"
                  value={formData.category}
                  onChange={handleChange}
                  className="flex-1 border border-gray-300 rounded-md px-3 py-2"
                  disabled={loadingCategories}
                >
                  <option value="">Pilih Kategori</option>
                  {categories.map((cat) => (
                    <option key={cat.id} value={cat.name}>
                      {cat.name}
                    </option>
                  ))}
                </select>
                <button
                  type="button"
                  onClick={() => setShowAddCategory(true)}
                  className="px-3 py-2 border border-gray-300 rounded-md bg-gray-50 hover:bg-gray-100 text-gray-600"
                  title="Tambah Kategori Baru"
                >
                  +
                </button>
              </>
            ) : (
              <div className="flex-1 flex gap-2">
                <input
                  type="text"
                  value={newCategoryName}
                  onChange={(e) => setNewCategoryName(e.target.value)}
                  placeholder="Nama Kategori Baru"
                  className="flex-1 border border-gray-300 rounded-md px-3 py-2"
                  autoFocus
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      handleAddCategory();
                    }
                  }}
                />
                <button
                  type="button"
                  onClick={handleAddCategory}
                  className="px-3 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                  disabled={loadingCategories}
                >
                  Simpan
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowAddCategory(false);
                    setNewCategoryName('');
                  }}
                  className="px-3 py-2 border border-gray-300 rounded-md bg-white hover:bg-gray-50"
                  disabled={loadingCategories}
                >
                  Batal
                </button>
              </div>
            )}
          </div>
          {loadingCategories && <p className="text-xs text-gray-500 mt-1">Memuat kategori...</p>}
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
        <div className="bg-gray-50 p-4 rounded-md border border-gray-200">
          <label className="block text-sm font-medium text-gray-700 mb-3">
            Lokasi Canvassing <span className="text-red-500">*</span>
          </label>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Province */}
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">
                Provinsi
              </label>
              <select
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                onChange={(e) => {
                  const [id, name] = e.target.value.split('|');
                  handleProvinceChange(id, name);
                }}
                disabled={loadingLocation}
              >
                <option value="">Pilih Provinsi</option>
                {provinces.map((p) => (
                  <option key={p.id} value={`${p.id}|${p.name}`}>
                    {p.name}
                  </option>
                ))}
              </select>
            </div>

            {/* City */}
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">
                Kota/Kabupaten
              </label>
              <select
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                onChange={(e) => {
                  const [id, name] = e.target.value.split('|');
                  handleCityChange(id, name);
                }}
                disabled={!cities.length}
              >
                <option value="">
                  {formData.city ? formData.city : 'Pilih Kota/Kabupaten'}
                </option>
                {cities.map((c) => (
                  <option key={c.id} value={`${c.id}|${c.name}`}>
                    {c.name}
                  </option>
                ))}
              </select>
              {isEdit && formData.city && <p className="text-xs text-indigo-600 mt-1">Saat ini: {formData.city}</p>}
            </div>

            {/* District */}
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">
                Kecamatan
              </label>
              <select
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                onChange={(e) => {
                  const [id, name] = e.target.value.split('|');
                  handleDistrictChange(id, name);
                }}
                disabled={!districts.length}
              >
                <option value="">
                  {formData.district ? formData.district : 'Pilih Kecamatan'}
                </option>
                {districts.map((d) => (
                  <option key={d.id} value={`${d.id}|${d.name}`}>
                    {d.name}
                  </option>
                ))}
              </select>
              {isEdit && formData.district && <p className="text-xs text-indigo-600 mt-1">Saat ini: {formData.district}</p>}
            </div>

            {/* Village */}
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">
                Kelurahan
              </label>
              <select
                className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"
                onChange={(e) => {
                  const [id, name] = e.target.value.split('|');
                  handleVillageChange(id, name);
                }}
                disabled={!villages.length}
              >
                <option value="">
                  {formData.village ? formData.village : 'Pilih Kelurahan'}
                </option>
                {villages.map((v) => (
                  <option key={v.id} value={`${v.id}|${v.name}`}>
                    {v.name}
                  </option>
                ))}
              </select>
              {isEdit && formData.village && <p className="text-xs text-indigo-600 mt-1">Saat ini: {formData.village}</p>}
            </div>
            
            {/* Loading Indicator */}
            {loadingLocation && (
               <div className="flex items-center text-xs text-gray-500 col-span-1 md:col-span-2">
                 <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                   <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                   <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                 </svg>
                 Memuat wilayah...
               </div>
            )}
          </div>
        </div>

        {/* Staff Assignment (Create Only) */}
        {!isEdit && (
          <div className="bg-gray-50 p-4 rounded-md border border-gray-200">
             <div className="flex justify-between items-center mb-3">
              <label className="block text-sm font-medium text-gray-700">
                Tugaskan Staff (Opsional)
              </label>
              <span className="text-xs text-gray-500">
                {selectedStaffIds.size} dipilih
              </span>
            </div>

            {loadingStaff ? (
              <div className="text-center py-4 text-sm text-gray-500">Memuat ketersediaan staff...</div>
            ) : !formData.start_date || !formData.end_date || !formData.city ? (
              <div className="text-center py-4 text-sm text-gray-400 italic">
                Lengkapi tanggal dan lokasi untuk melihat staff yang tersedia.
              </div>
            ) : availableStaff.length === 0 ? (
               <div className="text-center py-4 text-sm text-gray-500">Tidak ada staff tersedia.</div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto pr-2">
                {availableStaff.map(staff => (
                  <div 
                    key={staff.id}
                    className={`flex items-start p-2 border rounded-md transition-colors cursor-pointer ${
                      !staff.available ? 'bg-gray-100 opacity-60 cursor-not-allowed' : 
                      selectedStaffIds.has(staff.id) ? 'bg-indigo-50 border-indigo-300' : 'bg-white border-gray-200 hover:border-indigo-300'
                    }`}
                    onClick={() => staff.available && handleStaffToggle(staff.id)}
                  >
                    <input
                      type="checkbox"
                      checked={selectedStaffIds.has(staff.id)}
                      onChange={() => {}} // Handled by div click
                      disabled={!staff.available}
                      className="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <div className="ml-3 text-sm">
                      <div className={`font-medium ${selectedStaffIds.has(staff.id) ? 'text-indigo-700' : 'text-gray-700'}`}>
                        {staff.name}
                      </div>
                      <div className="text-xs text-gray-500">{staff.email}</div>
                      {!staff.available && (
                         <div className="text-xs text-red-500 mt-1">
                           Conflict: {staff.conflict?.group_name} ({staff.conflict?.city})
                         </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

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
