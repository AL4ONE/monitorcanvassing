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

    // View state
    const [previewImage, setPreviewImage] = useState(null);
    const [historyModal, setHistoryModal] = useState(null); // cycle id or null
    const [logs, setLogs] = useState([]);

    // Editing state
    const [editingId, setEditingId] = useState(null);
    const [editForm, setEditForm] = useState({
        status: '',
        next_followup_date: '',
        next_action: '',
        failure_reason: '',
        failure_notes: ''
    });

    useEffect(() => {
        loadStaffList();
        fetchReport();
    }, []);

    useEffect(() => {
        fetchReport();
    }, [selectedStaff, startDate, endDate]);

    const loadStaffList = async () => {
        try {
            const res = await api.get('/dashboard');
            if (res.data.staff_stats) {
                setUsers(res.data.staff_stats.map(s => s.staff));
            }
        } catch (e) {
            console.error(e);
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

    const handleEditClick = (cycle) => {
        setEditingId(cycle.id);
        setEditForm({
            status: cycle.status,
            next_followup_date: cycle.next_followup_date !== '-' ? cycle.next_followup_date : '',
            next_action: cycle.next_action !== '-' ? cycle.next_action : '',
            failure_reason: cycle.failure_reason || '',
            failure_notes: cycle.failure_notes || ''
        });
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        setEditForm({ status: '', next_followup_date: '', next_action: '', failure_reason: '', failure_notes: '' });
    };

    const handleSaveEdit = async (id) => {
        try {
            setLoading(true);
            const response = await api.patch(`/canvassing/${id}/status`, editForm);
            alert('Berhasil memperbarui data');
            setEditingId(null);
            fetchReport(); // Refresh data
        } catch (error) {
            console.error('Error saving edit:', error);
            alert('Gagal menyimpan: ' + (error.response?.data?.message || error.message));
        } finally {
            setLoading(false);
        }
    };

    const handleShowHistory = (cycle) => {
        setLogs(cycle.logs || []);
        setHistoryModal(cycle.id);
    };

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
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">Merchant</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Kontak</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Link IG</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Staff / Kategori</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status & Action</th>
                            <th className="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                            {/* Dynamic Columns for Stages */}
                            {[...Array(8)].map((_, i) => (
                                <th key={i} className="px-2 py-3 text-center font-medium text-gray-500 uppercase tracking-wider border-l min-w-[100px]">
                                    {i === 0 ? 'Canvassing' : `FU ${i}`}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {loading ? (
                            <tr><td colSpan="15" className="text-center py-4">Memuat data...</td></tr>
                        ) : reportData.length === 0 ? (
                            <tr><td colSpan="15" className="text-center py-4">Tidak ada data</td></tr>
                        ) : (
                            reportData.map((row) => (
                                <tr key={row.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-4 whitespace-normal font-medium text-gray-900 sticky left-0 bg-white z-10 shadow-sm">
                                        <div>{row.merchant_name}</div>
                                        <button
                                            onClick={() => handleShowHistory(row)}
                                            className="text-xs text-blue-600 hover:text-blue-800 mt-1 underline"
                                        >
                                            Lihat History
                                        </button>
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-gray-500">
                                        {row.contact_number}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-gray-500">
                                        {row.instagram_link ? (
                                            <a
                                                href={row.instagram_link}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-blue-600 hover:underline flex items-center gap-1"
                                            >
                                                Lihat IG →
                                            </a>
                                        ) : (
                                            <span className="text-gray-400">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-gray-500">
                                        {row.channel || '-'}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-gray-500">
                                        <div className="font-medium">{row.staff_name}</div>
                                        <div className="text-xs text-gray-400">{row.category}</div>
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap align-top">
                                        {editingId === row.id ? (
                                            <div className="flex flex-col gap-2 min-w-[200px]">
                                                <select
                                                    value={editForm.status}
                                                    onChange={e => setEditForm({ ...editForm, status: e.target.value })}
                                                    className="text-xs border rounded p-1"
                                                >
                                                    <option value="active">Active</option>
                                                    <option value="ongoing">Ongoing</option>
                                                    <option value="converted">Converted</option>
                                                    <option value="rejected">Rejected</option>
                                                </select>

                                                {(editForm.status === 'rejected' || editForm.status === 'failed' || editForm.status === 'invalid') && (
                                                    <>
                                                        <select
                                                            value={editForm.failure_reason}
                                                            onChange={e => setEditForm({ ...editForm, failure_reason: e.target.value })}
                                                            className="text-xs border rounded p-1"
                                                        >
                                                            <option value="">Pilih Alasan Gagal...</option>
                                                            <option value="Tidak Respon">Tidak Respon</option>
                                                            <option value="Tidak Tertarik">Tidak Tertarik</option>
                                                            <option value="Sudah Pakai Kompetitor">Sudah Pakai Kompetitor</option>
                                                            <option value="Lainnya">Lainnya</option>
                                                        </select>
                                                        <textarea
                                                            placeholder="Notes alasan gagal..."
                                                            value={editForm.failure_notes}
                                                            onChange={e => setEditForm({ ...editForm, failure_notes: e.target.value })}
                                                            className="text-xs border rounded p-1 h-16"
                                                        />
                                                    </>
                                                )}

                                                <input
                                                    type="text"
                                                    placeholder="Next Action..."
                                                    value={editForm.next_action}
                                                    onChange={e => setEditForm({ ...editForm, next_action: e.target.value })}
                                                    className="text-xs border rounded p-1"
                                                />
                                                <div className="flex gap-1 justify-end mt-1">
                                                    <button onClick={handleCancelEdit} className="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                                                    <button onClick={() => handleSaveEdit(row.id)} className="text-xs bg-blue-600 text-white px-2 py-1 rounded">Save</button>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col gap-1 items-start">
                                                <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    ${['converted', 'success'].includes(row.status) ? 'bg-green-100 text-green-800' :
                                                        ['rejected', 'failed', 'invalid'].includes(row.status) ? 'bg-red-100 text-red-800' :
                                                            'bg-blue-100 text-blue-800'}`}>
                                                    {row.status}
                                                </span>
                                                {row.failure_reason && (
                                                    <div className="text-xs text-red-600 font-medium">
                                                        {row.failure_reason}
                                                    </div>
                                                )}
                                                {row.failure_notes && (
                                                    <div className="text-xs text-gray-500 italic max-w-[150px] truncate" title={row.failure_notes}>
                                                        "{row.failure_notes}"
                                                    </div>
                                                )}
                                                {row.next_action !== '-' && (
                                                    <div className="text-xs text-gray-600 max-w-[150px] truncate" title={row.next_action}>
                                                        Action: {row.next_action}
                                                    </div>
                                                )}
                                                <button onClick={() => handleEditClick(row)} className="text-xs text-gray-400 hover:text-gray-600 underline">
                                                    Edit
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-4 whitespace-nowrap text-xs text-gray-500">
                                        {editingId === row.id ? (
                                            <input
                                                type="date"
                                                value={editForm.next_followup_date}
                                                onChange={e => setEditForm({ ...editForm, next_followup_date: e.target.value })}
                                                className="border rounded p-1 w-full"
                                            />
                                        ) : (
                                            <>
                                                <div>Start: {row.start_date}</div>
                                                <div>Next: {row.next_followup_date}</div>
                                                {row.last_followup_date !== '-' && <div className="text-gray-400">Last: {row.last_followup_date}</div>}
                                            </>
                                        )}
                                    </td>

                                    {/* Stages Cells */}
                                    {[...Array(8)].map((_, i) => {
                                        const stageData = row.stages[i];
                                        return (
                                            <td key={i} className="px-2 py-4 whitespace-nowrap text-center border-l align-top">
                                                {stageData ? (
                                                    <div className="flex flex-col items-center gap-1">
                                                        <div
                                                            className="w-12 h-16 bg-gray-100 rounded cursor-pointer overflow-hidden border hover:border-indigo-500 shadow-sm"
                                                            onClick={() => setPreviewImage(stageData.screenshot_url)}
                                                        >
                                                            <img
                                                                src={stageData.screenshot_url}
                                                                alt={`Stage ${i}`}
                                                                className="w-full h-full object-cover"
                                                                loading="lazy"
                                                            />
                                                        </div>
                                                        <span className="text-[10px] text-gray-500">{stageData.date.split('-').slice(1).join('/')}</span>
                                                        <span className={`text-[9px] px-1 rounded ${stageData.status === 'valid' ? 'bg-green-50 text-green-700' :
                                                            stageData.status === 'invalid' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700'
                                                            }`}>
                                                            {stageData.status && stageData.status.charAt(0).toUpperCase()}
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

            {/* History Modal */}
            {historyModal && (
                <div
                    className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
                    onClick={() => setHistoryModal(null)}
                >
                    <div className="bg-white rounded-lg p-6 max-w-lg w-full max-h-[80vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-bold">Riwayat Status</h2>
                            <button onClick={() => setHistoryModal(null)} className="text-gray-500 hover:text-gray-700">✕</button>
                        </div>
                        {logs.length === 0 ? (
                            <p className="text-gray-500">Belum ada riwayat perubahan.</p>
                        ) : (
                            <ul className="space-y-4">
                                {logs.map((log, idx) => (
                                    <li key={idx} className="border-b pb-2 last:border-0">
                                        <div className="flex justify-between text-sm">
                                            <span className="font-semibold">{log.date}</span>
                                            <span className="text-gray-500">{log.by}</span>
                                        </div>
                                        <div className="mt-1 text-sm">
                                            Status: <span className="line-through text-gray-400">{log.old}</span>{' '}
                                            <span className="text-gray-600">→</span>{' '}
                                            <span className="font-medium text-blue-600">{log.new}</span>
                                        </div>
                                        {/* Since backend implementation of logs returning notes wasn't fully checked, we assume it might not be in the 'logs' map in controller unless added. 
                                            Let's check controller again. It didn't map notes. 
                                            We can update controller to map notes or just skip notes for now. 
                                            User requirement was "History perubahan status (tanggal jam)". 
                                            It didn't explicitly ask for notes, but notes helps. 
                                            I'll leave it as is for now.
                                        */}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
