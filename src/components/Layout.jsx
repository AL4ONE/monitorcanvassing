import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Layout({ children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="bg-white shadow-md">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex">
              <div className="flex-shrink-0 flex items-center">
                <img
                  className="h-12 w-auto"
                  src="/logo_mcs.png"
                  alt="MCS Logo"
                />
              </div>
              <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
                <Link
                  to="/dashboard"
                  className={`${location.pathname === '/dashboard'
                    ? 'border-indigo-500 text-gray-900'
                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                    } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                >
                  Dashboard
                </Link>
                {user?.role === 'staff' && (
                  <>
                    <Link
                      to="/upload"
                      className={`${location.pathname === '/upload'
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Upload
                    </Link>
                    <Link
                      to="/bulk-import"
                      className={`${location.pathname === '/bulk-import'
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Bulk Import
                    </Link>
                    <Link
                      to="/my-canvassing-groups"
                      className={`${location.pathname.startsWith('/my-canvassing-groups') || location.pathname.startsWith('/canvassing-execution')
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Tugas Canvassing
                    </Link>
                    <Link
                      to="/online-canvassing"
                      className={`${location.pathname === '/online-canvassing'
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Report Online
                    </Link>
                  </>
                )}
                {user?.role === 'supervisor' && (
                  <>
                    <Link
                      to="/quality-check"
                      className={`${location.pathname === '/quality-check'
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Quality Check
                    </Link>
                    <Link
                      to="/report"
                      className={`${location.pathname === '/report'
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Laporan
                    </Link>
                    <Link
                      to="/bulk-import"
                      className={`${location.pathname === '/bulk-import'
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Bulk Import
                    </Link>
                    <Link
                      to="/canvassing-groups"
                      className={`${location.pathname.startsWith('/canvassing-groups')
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Canvassing Groups
                    </Link>
                    <Link
                      to="/supervisor-online-canvassing"
                      className={`${location.pathname === '/supervisor-online-canvassing'
                        ? 'border-indigo-500 text-gray-900'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                        } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                    >
                      Report Online (Direct)
                    </Link>
                  </>
                )}
              </div>
            </div>
            <div className="flex items-center">
              <span className="text-sm text-gray-700 mr-4">
                {user?.name} ({user?.role === 'supervisor' ? 'Supervisor' : 'Staff'})
              </span>
              <button
                onClick={handleLogout}
                className="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700"
              >
                Logout
              </button>
            </div>
          </div>
        </div>
      </nav>

      <main className="py-6">{children}</main>
    </div>
  );
}



