/**
 * Compress image file using Browser Canvas API
 * @param {File} file - The image file to compress
 * @param {number} maxWidth - Maximum width (default 1280px)
 * @param {number} quality - JPEG quality 0-1 (default 0.7)
 * @returns {Promise<File>} - Compressed file
 */
export const compressImage = (file, maxWidth = 1280, quality = 0.7) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
      const img = new Image();
      // Set onload BEFORE src to avoid race conditions
      img.onload = () => {
        const elem = document.createElement('canvas');
        let width = img.width;
        let height = img.height;

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
      img.onerror = (error) => reject(error);
      // Trigger load
      img.src = event.target.result;
    };
    reader.onerror = (error) => reject(error);
  });
};
