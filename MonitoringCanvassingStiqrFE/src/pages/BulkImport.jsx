import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api';

export default function BulkImport() {
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [result, setResult] = useState(null);
    const [dragActive, setDragActive] = useState(false);
    const fileInputRef = useRef(null);
    const navigate = useNavigate();

    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    };

    const handleFileChange = (e) => {
        if (e.target.files && e.target.files[0]) {
            handleFileSelect(e.target.files[0]);
        }
    };

    const handleFileSelect = (selectedFile) => {
        const validTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
            'text/csv'
        ];

        if (!validTypes.includes(selectedFile.type)) {
            alert('Format file tidak valid. Gunakan Excel (.xlsx, .xls) atau CSV');
            return;
        }

        if (selectedFile.size > 10 * 1024 * 1024) { // 10MB
            alert('File terlalu besar. Maksimal 10MB');
            return;
        }

        setFile(selectedFile);
        setResult(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!file) {
            alert('Pilih file terlebih dahulu');
            return;
        }

        setUploading(true);
        setResult(null);

        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await api.post('/import/spreadsheet', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            setResult(response.data.data);

            // Reset file if successful
            if (response.data.data.failed === 0) {
                setFile(null);
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            }
        } catch (error) {
            const errorMessage = error.response?.data?.message || 'Import gagal. Silakan coba lagi.';
            setResult({
                total_rows: 0,
                imported: 0,
                failed: 0,
                errors: [{ row: 0, merchant: 'System', error: errorMessage }]
            });
        } finally {
            setUploading(false);
        }
    };

    const handleDownloadTemplate = async () => {
        try {
            const response = await api.get('/import/template', {
                responseType: 'blob',
            });

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'template_import_canvassing.xlsx');
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch (error) {
            alert('Gagal download template');
        }
    };

    const formatFileSize = (bytes) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    return (
        <div className="max-w-6xl mx-auto p-6">
            <div className="mb-6 flex justify-between items-center">
                <h1 className="text-2xl font-bold">Bulk Import Data Canvassing</h1>
                <button
                    onClick={handleDownloadTemplate}
                    className="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 flex items-center gap-2"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download Template
                </button>
            </div>

            <div className="bg-white rounded-lg shadow-md p-6 mb-6">
                <div className="mb-4">
                    <h2 className="text-lg font-semibold mb-2">Instruksi</h2>
                    <ul className="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li>Download template Excel terlebih dahulu</li>
                        <li>Isi data sesuai format: Nama Merchant, Kategori, Jenis Usaha, Channel, Link IG, Status, Catatan</li>
                        <li>Masukkan foto screenshot di kolom yang sesuai (SS Chat untuk canvassing, fu1-fu7 untuk follow up)</li>
                        <li>Upload file Excel yang sudah diisi</li>
                        <li>Maksimal ukuran file: 10MB</li>
                        <li>Format yang didukung: .xlsx, .xls, .csv</li>
                    </ul>
                </div>

                <form onSubmit={handleSubmit}>
                    <div
                        className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${dragActive
                                ? 'border-indigo-500 bg-indigo-50'
                                : 'border-gray-300 hover:border-gray-400'
                            }`}
                        onDragEnter={handleDrag}
                        onDragLeave={handleDrag}
                        onDragOver={handleDrag}
                        onDrop={handleDrop}
                    >
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={handleFileChange}
                            className="hidden"
                            id="file-upload"
                        />

                        {!file ? (
                            <label htmlFor="file-upload" className="cursor-pointer">
                                <svg
                                    className="mx-auto h-12 w-12 text-gray-400"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 48 48"
                                >
                                    <path
                                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                        strokeWidth={2}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </svg>
                                <p className="mt-2 text-sm text-gray-600">
                                    <span className="font-semibold text-indigo-600">Klik untuk pilih file</span> atau drag & drop
                                </p>
                                <p className="text-xs text-gray-500 mt-1">Excel (.xlsx, .xls) atau CSV (maks 10MB)</p>
                            </label>
                        ) : (
                            <div className="space-y-2">
                                <svg
                                    className="mx-auto h-12 w-12 text-green-500"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <p className="text-sm font-medium text-gray-900">{file.name}</p>
                                <p className="text-xs text-gray-500">{formatFileSize(file.size)}</p>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setFile(null);
                                        if (fileInputRef.current) fileInputRef.current.value = '';
                                    }}
                                    className="text-sm text-red-600 hover:text-red-800"
                                >
                                    Hapus file
                                </button>
                            </div>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={uploading || !file}
                        className="w-full mt-6 bg-indigo-600 text-white py-3 px-4 rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                    >
                        {uploading ? (
                            <span className="flex items-center justify-center gap-2">
                                <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                Mengimport data...
                            </span>
                        ) : (
                            'Import Data'
                        )}
                    </button>
                </form>
            </div>

            {result && (
                <div className="bg-white rounded-lg shadow-md p-6">
                    <h2 className="text-lg font-semibold mb-4">Hasil Import</h2>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p className="text-sm text-blue-600 font-medium">Total Rows</p>
                            <p className="text-2xl font-bold text-blue-900">{result.total_rows}</p>
                        </div>
                        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                            <p className="text-sm text-green-600 font-medium">Berhasil</p>
                            <p className="text-2xl font-bold text-green-900">{result.imported}</p>
                        </div>
                        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                            <p className="text-sm text-red-600 font-medium">Gagal</p>
                            <p className="text-2xl font-bold text-red-900">{result.failed}</p>
                        </div>
                    </div>

                    {result.errors && result.errors.length > 0 && (
                        <div>
                            <h3 className="text-md font-semibold mb-2 text-red-700">Detail Error</h3>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 border">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Merchant</th>
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Error</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {result.errors.map((error, index) => (
                                            <tr key={index}>
                                                <td className="px-4 py-2 text-sm text-gray-900">{error.row}</td>
                                                <td className="px-4 py-2 text-sm text-gray-900">{error.merchant}</td>
                                                <td className="px-4 py-2 text-sm text-red-600">{error.error}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {result.failed === 0 && (
                        <div className="mt-4 flex gap-4">
                            <button
                                onClick={() => navigate('/dashboard')}
                                className="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700"
                            >
                                Lihat Dashboard
                            </button>
                            <button
                                onClick={() => {
                                    setResult(null);
                                    setFile(null);
                                    if (fileInputRef.current) fileInputRef.current.value = '';
                                }}
                                className="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700"
                            >
                                Import Lagi
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
