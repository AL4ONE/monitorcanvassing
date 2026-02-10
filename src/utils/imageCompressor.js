import imageCompression from 'browser-image-compression';

/**
 * Compress image using browser-image-compression library
 * Tuned for OCR readability: higher resolution + quality
 * @param {File} file - The image file to compress
 * @returns {Promise<File>} - Compressed file (~300-500KB)
 */
export const compressImage = async (file) => {
  // If file is already < 500KB, skip compression
  if (file.size / 1024 / 1024 < 0.5) {
    return file;
  }

  const options = {
    maxSizeMB: 0.5,              // Target ~500KB max (server limit)
    maxWidthOrHeight: 1920,      // Full HD is enough for OCR
    useWebWorker: false,         // Main thread for stability
    fileType: 'image/jpeg',
    initialQuality: 0.80,        // Good balance for text
    preserveExif: false,
  };

  try {
    const compressedFile = await imageCompression(file, options);
    return compressedFile;
  } catch (error) {
    console.error('Compression failed:', error);
    return file; // Fallback
  }
};
