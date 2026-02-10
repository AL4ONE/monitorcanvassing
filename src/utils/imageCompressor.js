/**
 * Compress image file using Browser Canvas API
 * Refactored to use URL.createObjectURL for better mobile stability
 * @param {File} file - The image file to compress
 * @param {number} maxWidth - Maximum width (default 1280px)
 * @param {number} quality - JPEG quality 0-1 (default 0.7)
 * @returns {Promise<File>} - Compressed file
 */
export const compressImage = (file, maxWidth = 1280, quality = 0.7) => {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const objectUrl = URL.createObjectURL(file);

    img.onload = () => {
      // Clean up memory
      URL.revokeObjectURL(objectUrl);

      const elem = document.createElement('canvas');
      let width = img.width;
      let height = img.height;

      // Safety check for 0 dimensions
      if (width === 0 || height === 0) {
        reject(new Error("Image has 0 dimensions"));
        return;
      }

      // Calculate new dimensions
      if (width > maxWidth) {
        height = Math.round((height * maxWidth) / width);
        width = maxWidth;
      }

      elem.width = width;
      elem.height = height;

      const ctx = elem.getContext('2d');
      // Fill white background to prevent black image for transparent PNGs
      ctx.fillStyle = '#FFFFFF';
      ctx.fillRect(0, 0, width, height);

      ctx.drawImage(img, 0, 0, width, height);

      // Compress
      ctx.canvas.toBlob(
        (blob) => {
          if (!blob) {
            reject(new Error("Canvas toBlob failed"));
            return;
          }
          const compressedFile = new File([blob], file.name, {
            type: 'image/jpeg',
            lastModified: Date.now(),
          });
          resolve(compressedFile);
        },
        'image/jpeg',
        quality
      );
    };

    img.onerror = (error) => {
      URL.revokeObjectURL(objectUrl);
      reject(error);
    };

    // Load image
    img.src = objectUrl;
  });
};
