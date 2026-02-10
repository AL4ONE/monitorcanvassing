import imageCompression from 'browser-image-compression';

/**
 * Compress image using browser-image-compression library
 * Battle-tested on thousands of mobile devices
 * @param {File} file - The image file to compress
 * @param {number} maxSizeMB - Max output file size in MB (default 0.15 = ~150KB)
 * @param {number} maxWidthOrHeight - Max width or height (default 1024px)
 * @returns {Promise<File>} - Compressed file
 */
export const compressImage = async (file, maxWidthOrHeight = 1024, quality = 0.6) => {
  const options = {
    maxSizeMB: 0.15,             // Target ~150KB
    maxWidthOrHeight: maxWidthOrHeight,
    useWebWorker: true,          // Use web worker for better performance
    fileType: 'image/jpeg',      // Always output JPEG
    initialQuality: quality,
  };

  const compressedFile = await imageCompression(file, options);
  return compressedFile;
};
