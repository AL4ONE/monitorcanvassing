/**
 * Compress image file using createImageBitmap + Canvas
 * Most robust method - handles HEIC, WebP, etc. natively
 * @param {File} file - The image file to compress
 * @param {number} maxWidth - Maximum width (default 1024px)
 * @param {number} quality - JPEG quality 0-1 (default 0.6)
 * @returns {Promise<File>} - Compressed file
 */
export const compressImage = async (file, maxWidth = 1024, quality = 0.6) => {
  // createImageBitmap handles decoding ANY image format the browser supports
  const bitmap = await createImageBitmap(file);

  let width = bitmap.width;
  let height = bitmap.height;

  if (width === 0 || height === 0) {
    bitmap.close();
    throw new Error("Image has 0 dimensions");
  }

  // Calculate new dimensions (only downscale, never upscale)
  if (width > maxWidth) {
    height = Math.round((height * maxWidth) / width);
    width = maxWidth;
  }

  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;

  const ctx = canvas.getContext('2d');
  // White background (prevents transparent PNG -> black JPEG)
  ctx.fillStyle = '#FFFFFF';
  ctx.fillRect(0, 0, width, height);
  // Draw the decoded bitmap
  ctx.drawImage(bitmap, 0, 0, width, height);
  bitmap.close(); // Free memory

  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (!blob) {
          reject(new Error("Canvas toBlob failed"));
          return;
        }
        const compressedFile = new File([blob], file.name.replace(/\.[^.]+$/, '.jpg'), {
          type: 'image/jpeg',
          lastModified: Date.now(),
        });
        resolve(compressedFile);
      },
      'image/jpeg',
      quality
    );
  });
};
