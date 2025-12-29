import { useState, useEffect } from 'react';
import api from '../api';

export default function Report() {
    const [reportData, setReportData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [users, setUsers] = useState([]);

    // Filters
    const [selectedStaff, setSelectedStaff] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    // View state for specific image
    const [previewImage, setPreviewImage] = useState(null);

    useEffect(() => {
        fetchUsers();
        fetchReport();
    }, []); // Initial load

    useEffect(() => {
        fetchReport();
    }, [selectedStaff, startDate, endDate]);

    const fetchUsers = async () => {
        try {
            // Assuming we can get staff list from existing endpoint or we might need a new one
            // For now let's hope existing endpoint structure supports relevant user listing or just rely on IDs
            // Actually we know there is no specific "get all staff" public endpoint easily accessible without auth, 
            // but SupervisorDashboard uses logic inside DashboardController to get stats.
            // Let's create a simple staff fetch or just hardcode if needed, but better to fetch.
            // Wait, we don't have a clean "get all staff" endpoint yet? 
            // SupervisorDashboard uses `dashboard` endpoint to get staff stats. We can reuse that or add one.
            // For now, let's just fetch report data and extract unique staff from it if we want to be lazy, 
            // OR better, let's implement a proper fetch if needed. 
            // Actually, let's look at `DashboardController` - `supervisorDashboard` returns staff list in `staff_stats`.
            // We can use that if we want.
            // Or just try to hit an endpoint.
            // We will skip filling the dropdown for now or try to mock it.
            // *Correction*: We can just fetch report and populate filter? No, we need filter to fetch.
            // Let's assume we can fetch report without filters first.
        } catch (error) {
            console.error("Error fetching users", error);
        }
    };

    const fetchReport = async () => {
        try {
            setLoading(true);
            const params = {};
            if (selectedStaff) params.staff_id = selectedStaff;
            if (startDate) params.start_date = startDate;
            if (endDate) params.end_date = endDate;

            const response = await api.get('/canvassing/report', { params });
            setReportData(response.data);
        } catch (error) {
            console.error('Error fetching report:', error);
            alert('Gagal memuat laporan');
        } finally {
            setLoading(false);
        }
    };

    const handleCleanupValid = async () => {
        if (!window.confirm('PERINGATAN: Apakah Anda yakin ingin menghapus SEMUA data yang statusnya VALID? Data ini akan hilang dari laporan dan tidak dapat dikembalikan.')) {
            return;
        }

        try {
            setLoading(true);
            const response = await api.delete('/canvassing/cleanup-valid');
            alert(response.data.message);
            fetchReport();
        } catch (error) {
            console.error('Error cleanup valid:', error);
            alert('Gagal menghapus data: ' + (error.response?.data?.message || error.message));
        } finally {
            setLoading(false);
        }
    };

    // Extract unique staff for filter from loaded data if we don't have a dedicated endpoint yet
    // This is a bit chicken-and-egg if we filter on backend.
    // Ideally we should add a Reference API. For now, let's just use text input or simple ID?
    // Let's try to get staff list from dashboard endpoint just once.
    const loadStaffList = async () => {
        try {
            const res = await api.get('/dashboard'); // This returns staff_stats
            if (res.data.staff_stats) {
                setUsers(res.data.staff_stats.map(s => s.staff));
            }
        } catch (e) {
            console.error(e);
        }
    };

    useEffect(() => {
        loadStaffList();
    }, []);

    return (
        <div className="max-w-full mx-auto p-4">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">Laporan Canvassing</h1>
                <button
                    onClick={handleCleanupValid}
                    className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm font-medium"
                >
                    Hapus Data Valid
                </button>
            </div>

            {/* Filters */}
            <div className="bg-white p-4 rounded-lg shadow mb-6 flex flex-wrap gap-4 items-end">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Staff</label>
                    <select
                        value={selectedStaff}
                        onChange={(e) => setSelectedStaff(e.target.value)}
                        className="border border-gray-300 rounded-md px-3 py-2 min-w-[200px]"
                    >
                        <option value="">Semua Staff</option>
                        {users.map(u => (
                            <option key={u.id} value={u.id}>{u.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                    <input
                        type="date"
                        value={startDate}
                        onChange={(e) => setStartDate(e.target.value)}
                        className="border border-gray-300 rounded-md px-3 py-2"
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                    <input
                        type="date"
                        value={endDate}
                        onChange={(e) => setEndDate(e.target.value)}
                        className="border border-gray-300 rounded-md px-3 py-2"
                    />
                </div>
            </div>

            {/* Table */}
            <div className="bg-white rounded-lg shadow overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">Nama Merchant</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>

                            {/* Dynamic Columns for Stages */}
                            {[...Array(8)].map((_, i) => (
                                <th key={i} className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-l">
                                    {i === 0 ? 'Canvassing' : `FU ${i}`}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {loading ? (
                            <tr><td colSpan="12" className="text-center py-4">Memuat data...</td></tr>
                        ) : reportData.length === 0 ? (
                            <tr><td colSpan="12" className="text-center py-4">Tidak ada data</td></tr>
                        ) : (
                            reportData.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-white z-10 shadow-sm md:shadow-none">
                                        {row.merchant_name}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{row.staff_name}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{row.category}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm">
                                        <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                ${row.status === 'success' ? 'bg-green-100 text-green-800' :
                                                row.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}`}>
                                            {row.status}
                                        </span>
                                    </td>

                                    {/* Stages Cells */}
                                    {[...Array(8)].map((_, i) => {
                                        const stageData = row.stages[i];
                                        return (
                                            <td key={i} className="px-6 py-4 whitespace-nowrap text-center border-l align-top">
                                                {stageData ? (
                                                    <div className="flex flex-col items-center gap-1">
                                                        <div
                                                            className="w-16 h-24 bg-gray-200 rounded cursor-pointer overflow-hidden border hover:border-indigo-500"
                                                            onClick={() => setPreviewImage(stageData.screenshot_url)}
                                                        >
                                                            <img
                                                                src={stageData.screenshot_url}
                                                                alt={`Stage ${i}`}
                                                                className="w-full h-full object-cover"
                                                                loading="lazy"
                                                            />
                                                        </div>
                                                        <span className="text-xs text-gray-500">{stageData.date}</span>
                                                        <span className={`text-[10px] px-1 rounded ${stageData.status === 'valid' ? 'bg-green-100 text-green-700' :
                                                            stageData.status === 'invalid' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'
                                                            }`}>
                                                            {stageData.status}
                                                        </span>
                                                    </div>
                                                ) : '-'}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Image Preview Modal */}
            {previewImage && (
                <div
                    className="fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4"
                    onClick={() => setPreviewImage(null)}
                >
                    <div className="relative max-w-4xl max-h-[90vh] overflow-auto">
                        <button
                            className="absolute top-2 right-2 text-white bg-black bg-opacity-50 rounded-full p-2 hover:bg-opacity-75"
                            onClick={() => setPreviewImage(null)}
                        >
                            ✕
                        </button>
                        <img src={previewImage} alt="Preview" className="max-w-full max-h-[85vh] object-contain" />
                    </div>
                </div>
            )}
        </div>
    );
}
