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
    maxWidthOrHeight: 2560,      // 2.5K resolution (Best balance: Clear text + Low file size)
    useWebWorker: false,         // Main thread for stability on mobile
    fileType: 'image/jpeg',      // Always output JPEG
    initialQuality: 1.0,         // Max quality to avoid compression artifacts
    preserveExif: false,         // Strip metadata to save space
  };

  const compressedFile = await imageCompression(file, options);
  return compressedFile;
};

/**
 * Crop the top part of the image (header) for OCR accuracy
 * @param {File} file - Original file
 * @returns {Promise<Blob>} - Cropped image blob (high quality)
 */
export const cropImageHeader = async (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (event) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // We only need the top ~1500px (or full height if smaller)
        const cropHeight = Math.min(img.height, 1500); 
        const width = img.width;

        canvas.width = width;
        canvas.height = cropHeight;

        // Draw only the top part
        ctx.drawImage(img, 0, 0, width, cropHeight, 0, 0, width, cropHeight);

        // Export as High Quality JPEG (0.95)
        // This is small because it's only the header
        canvas.toBlob(
          (blob) => {
            if (blob) {
              resolve(blob);
            } else {
              reject(new Error('Canvas to Blob failed'));
            }
          },
          'image/jpeg',
          0.95 
        );
      };
      img.onerror = (err) => reject(err);
      img.src = event.target.result;
    };
    reader.onerror = (err) => reject(err);
    reader.readAsDataURL(file);
  });
};
