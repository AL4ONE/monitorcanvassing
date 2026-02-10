import imageCompression from 'browser-image-compression';

/**
 * Compress image using browser-image-compression library
 * Tuned for OCR readability: higher resolution + quality
 * @param {File} file - The image file to compress
 * @returns {Promise<File>} - Compressed file (~200-400KB)
 */
export const compressImage = async (file) => {
  const options = {
    maxSizeMB: 0.4,              // Target ~400KB (enough for OCR text readability)
    maxWidthOrHeight: 1600,      // Keep resolution high for sharp text
    useWebWorker: false,         // Main thread for stability on mobile
    fileType: 'image/jpeg',      // Always output JPEG
    initialQuality: 0.8,         // Higher quality for text clarity
    preserveExif: false,         // Strip metadata to save space
  };

  const compressedFile = await imageCompression(file, options);
  return compressedFile;
};
