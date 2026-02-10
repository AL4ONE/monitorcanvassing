import imageCompression from 'browser-image-compression';

/**
 * Compress image using browser-image-compression library
 * Tuned for OCR readability: higher resolution + quality
 * @param {File} file - The image file to compress
 * @returns {Promise<File>} - Compressed file (~300-500KB)
 */
export const compressImage = async (file) => {
  const options = {
    maxSizeMB: 2.0,              // Increased to 2MB to ensure max detail
    maxWidthOrHeight: 3840,      // 4K resolution limit (basically keep original size)
    useWebWorker: false,         // Main thread for stability on mobile
    fileType: 'image/jpeg',      // Always output JPEG
    initialQuality: 1.0,         // Max quality to avoid compression artifacts
    preserveExif: false,         // Strip metadata to save space
  };

  const compressedFile = await imageCompression(file, options);
  return compressedFile;
};
