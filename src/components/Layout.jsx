import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Layout({ children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const NavLink = ({ to, children, mobile = false }) => {
    const baseClasses = mobile
      ? "block pl-3 pr-4 py-2 border-l-4 text-base font-medium transition-colors duration-150"
      : "inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-150";

    const activeClasses = mobile
      ? "border-indigo-500 text-indigo-700 bg-indigo-50"
      : "border-indigo-500 text-gray-900";

    const inactiveClasses = mobile
      ? "border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800"
      : "border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700";

    const isActive = location.pathname === to || (to !== '/dashboard' && location.pathname.startsWith(to));

    return (
      <Link
        to={to}
        className={`${baseClasses} ${isActive ? activeClasses : inactiveClasses}`}
        onClick={() => mobile && setIsMobileMenuOpen(false)}
      >
        {children}
      </Link>
    );
  };

  const renderNavLinks = (mobile = false) => (
    <>
      <NavLink to="/dashboard" mobile={mobile}>Dashboard</NavLink>
      {user?.role === 'staff' && (
        <>
          <NavLink to="/upload" mobile={mobile}>Upload</NavLink>
          <NavLink to="/bulk-import" mobile={mobile}>Bulk Import</NavLink>
          <NavLink to="/my-canvassing-groups" mobile={mobile}>Tugas Canvassing</NavLink>
          <NavLink to="/online-canvassing" mobile={mobile}>Report Offline</NavLink>
        </>
      )}
      {user?.role === 'supervisor' && (
        <>
          <NavLink to="/quality-check" mobile={mobile}>Quality Check</NavLink>
          <NavLink to="/report" mobile={mobile}>Laporan</NavLink>
          <NavLink to="/bulk-import" mobile={mobile}>Bulk Import</NavLink>
          <NavLink to="/canvassing-groups" mobile={mobile}>Canvassing In Groups</NavLink>
          <NavLink to="/supervisor-online-canvassing" mobile={mobile}>Canvassing Out Groups (Direct)</NavLink>
        </>
      )}
    </>
  );

  return (
    <div className="min-h-screen bg-gray-50">
      <nav className="bg-white shadow-md">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex">
              {/* Mobile menu button */}
              <div className="-ml-2 mr-2 flex items-center sm:hidden">
                <button
                  onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                  className="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                >
                  <span className="sr-only">Open main menu</span>
                  {isMobileMenuOpen ? (
                    <svg className="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  ) : (
                    <svg className="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                  )}
                </button>
              </div>
              
              <div className="flex-shrink-0 flex items-center">
                <img
                  className="h-8 w-auto sm:h-10" // Adjusted size for mobile
                  src="/logo_mcs.png"
                  alt="MCS Logo"
                />
              </div>
              <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
                {renderNavLinks(false)}
              </div>
            </div>
            <div className="flex items-center">
              <span className="hidden sm:inline text-sm text-gray-700 mr-4">
                {user?.name} ({user?.role === 'supervisor' ? 'Supervisor' : 'Staff'})
              </span>
              <button
                onClick={handleLogout}
                className="bg-indigo-600 text-white px-3 py-1.5 sm:px-4 sm:py-2 rounded-md text-xs sm:text-sm hover:bg-indigo-700 transition-colors"
              >
                Logout
              </button>
            </div>
          </div>
        </div>

        {/* Mobile menu, show/hide based on menu state */}
        <div className={`${isMobileMenuOpen ? 'block' : 'hidden'} sm:hidden border-t border-gray-200`}>
          <div className="pt-2 pb-3 space-y-1">
            {renderNavLinks(true)}
          </div>
          <div className="pt-4 pb-4 border-t border-gray-200">
            <div className="flex items-center px-4">
              <div className="ml-3">
                <div className="text-base font-medium text-gray-800">{user?.name}</div>
                <div className="text-sm font-medium text-gray-500">{user?.role === 'supervisor' ? 'Supervisor' : 'Staff'}</div>
              </div>
            </div>
          </div>
        </div>
      </nav>

      <main className="py-6">{children}</main>
    </div>
  );
}



