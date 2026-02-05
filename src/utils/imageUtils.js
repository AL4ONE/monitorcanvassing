/**
 * Image compression utility for reducing upload file sizes
 * Uses HTML Canvas API to resize and compress images
 */

/**
 * Compress an image file to a target maximum size
 * @param {File} file - The original image file
 * @param {number} maxSizeMB - Maximum file size in MB (default: 1.5)
 * @param {number} maxDimension - Maximum width/height in pixels (default: 1920)
 * @returns {Promise<File>} - The compressed image file
 */
export async function compressImage(file, maxSizeMB = 1.5, maxDimension = 1920) {
  // If file is already small enough, return it as-is
  const maxSizeBytes = maxSizeMB * 1024 * 1024;
  if (file.size <= maxSizeBytes) {
    return file;
  }

  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    
    reader.onload = (event) => {
      const img = new Image();
      
      img.onload = () => {
        // Calculate new dimensions while maintaining aspect ratio
        let { width, height } = img;
        
        if (width > maxDimension || height > maxDimension) {
          if (width > height) {
            height = Math.round((height * maxDimension) / width);
            width = maxDimension;
          } else {
            width = Math.round((width * maxDimension) / height);
            height = maxDimension;
          }
        }

        // Create canvas and draw resized image
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        // Start with high quality and reduce until file size is acceptable
        let quality = 0.9;
        const minQuality = 0.3;
        const step = 0.1;

        const tryCompress = () => {
          canvas.toBlob(
            (blob) => {
              if (!blob) {
                reject(new Error('Failed to compress image'));
                return;
              }

              // If size is acceptable or quality is at minimum, return the blob
              if (blob.size <= maxSizeBytes || quality <= minQuality) {
                // Convert blob to File with original filename
                const compressedFile = new File(
                  [blob], 
                  file.name.replace(/\.[^/.]+$/, '.jpg'), // Change extension to .jpg
                  { type: 'image/jpeg' }
                );
                
                console.log(`Image compressed: ${(file.size / 1024 / 1024).toFixed(2)}MB → ${(compressedFile.size / 1024 / 1024).toFixed(2)}MB (quality: ${quality.toFixed(1)})`);
                resolve(compressedFile);
              } else {
                // Reduce quality and try again
                quality -= step;
                tryCompress();
              }
            },
            'image/jpeg',
            quality
          );
        };

        tryCompress();
      };

      img.onerror = () => {
        reject(new Error('Failed to load image'));
      };

      img.src = event.target.result;
    };

    reader.onerror = () => {
      reject(new Error('Failed to read file'));
    };

    reader.readAsDataURL(file);
  });
}

/**
 * Check if a file is an image
 * @param {File} file - The file to check
 * @returns {boolean} - True if the file is an image
 */
export function isImageFile(file) {
  return file && file.type.startsWith('image/');
}
