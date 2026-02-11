import imageCompression from 'browser-image-compression';

/**
 * Compress image using browser-image-compression library
 * Tuned for OCR readability: higher resolution + quality
 * @param {File} file - The image file to compress
 * @returns {Promise<File>} - Compressed file (~300-500KB)
 */
export const compressImage = async (file) => {
  const options = {
    maxSizeMB: 0.5,              // Set to 0.5MB (500KB) to fit strict server limits
    maxWidthOrHeight: 5120,      // 5K resolution (Balance between width & quality for 0.5MB limit)
    useWebWorker: false,         // Main thread for stability on mobile
    fileType: 'image/jpeg',      // Always output JPEG
    initialQuality: 1.0,         // Max quality to avoid compression artifacts
    preserveExif: false,         // Strip metadata to save space
  };

  const compressedFile = await imageCompression(file, options);
  return compressedFile;
};
