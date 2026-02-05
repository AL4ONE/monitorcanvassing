import { createContext, useContext, useState, useCallback } from 'react';

const ConfirmContext = createContext();

export function useConfirm() {
  const context = useContext(ConfirmContext);
  if (!context) {
    throw new Error('useConfirm must be used within ConfirmProvider');
  }
  return context;
}

export function ConfirmProvider({ children }) {
  const [confirmState, setConfirmState] = useState({
    isOpen: false,
    title: 'Konfirmasi',
    message: '',
    confirmText: 'Ya, Lanjutkan',
    cancelText: 'Batal',
    variant: 'danger', // 'danger' | 'warning' | 'info'
    onConfirm: null,
    onCancel: null,
  });

  const confirm = useCallback(({ 
    title = 'Konfirmasi', 
    message, 
    confirmText = 'Ya, Lanjutkan', 
    cancelText = 'Batal',
    variant = 'danger' 
  }) => {
    return new Promise((resolve) => {
      setConfirmState({
        isOpen: true,
        title,
        message,
        confirmText,
        cancelText,
        variant,
        onConfirm: () => {
          setConfirmState(prev => ({ ...prev, isOpen: false }));
          resolve(true);
        },
        onCancel: () => {
          setConfirmState(prev => ({ ...prev, isOpen: false }));
          resolve(false);
        },
      });
    });
  }, []);

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget && confirmState.onCancel) {
      confirmState.onCancel();
    }
  };

  const getVariantStyles = () => {
    switch (confirmState.variant) {
      case 'danger':
        return {
          icon: (
            <svg className="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          iconBg: 'bg-red-100',
          confirmBtn: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
        };
      case 'warning':
        return {
          icon: (
            <svg className="w-6 h-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          ),
          iconBg: 'bg-yellow-100',
          confirmBtn: 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500',
        };
      default:
        return {
          icon: (
            <svg className="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          ),
          iconBg: 'bg-blue-100',
          confirmBtn: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
        };
    }
  };

  const styles = getVariantStyles();

  return (
    <ConfirmContext.Provider value={{ confirm }}>
      {children}
      
      {/* Confirm Modal */}
      {confirmState.isOpen && (
        <div 
          className="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
          onClick={handleBackdropClick}
        >
          <div 
            className="bg-white rounded-xl shadow-2xl max-w-md w-full transform transition-all animate-modal-enter"
            onClick={e => e.stopPropagation()}
          >
            <div className="p-6">
              <div className="flex items-start gap-4">
                <div className={`flex-shrink-0 w-12 h-12 rounded-full ${styles.iconBg} flex items-center justify-center`}>
                  {styles.icon}
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="text-lg font-semibold text-gray-900">{confirmState.title}</h3>
                  <p className="mt-2 text-sm text-gray-600">{confirmState.message}</p>
                </div>
              </div>
            </div>
            
            <div className="flex gap-3 px-6 py-4 bg-gray-50 rounded-b-xl">
              <button
                onClick={confirmState.onCancel}
                className="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors"
              >
                {confirmState.cancelText}
              </button>
              <button
                onClick={confirmState.onConfirm}
                className={`flex-1 px-4 py-2.5 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors ${styles.confirmBtn}`}
              >
                {confirmState.confirmText}
              </button>
            </div>
          </div>
        </div>
      )}
    </ConfirmContext.Provider>
  );
}
