import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Login from './pages/Login';
import StaffDashboard from './pages/StaffDashboard';
import StaffUpload from './pages/StaffUpload';
import SupervisorDashboard from './pages/SupervisorDashboard';
import QualityCheck from './pages/QualityCheck';
import Layout from './components/Layout';

function PrivateRoute({ children, requiredRole }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="min-h-screen flex items-center justify-center">Memuat...</div>;
  }

  if (!user) {
    return <Navigate to="/login" />;
  }

  if (requiredRole && user.role !== requiredRole) {
    return <Navigate to="/dashboard" />;
  }

  return children;
}

function AppRoutes() {
  const { user } = useAuth();

  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to="/dashboard" /> : <Login />} />
      
      <Route
        path="/dashboard"
        element={
          <PrivateRoute>
            <Layout>
              {user?.role === 'supervisor' ? (
                <SupervisorDashboard />
              ) : (
                <StaffDashboard />
              )}
            </Layout>
          </PrivateRoute>
        }
      />
      
      <Route
        path="/upload"
        element={
          <PrivateRoute requiredRole="staff">
            <Layout>
              <StaffUpload />
            </Layout>
          </PrivateRoute>
        }
      />
      
      <Route
        path="/quality-check"
        element={
          <PrivateRoute requiredRole="supervisor">
            <Layout>
              <QualityCheck />
            </Layout>
          </PrivateRoute>
        }
      />
      
      <Route path="/" element={<Navigate to="/dashboard" />} />
    </Routes>
  );
}

function App() {
  return (
    <Router>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </Router>
  );
}

export default App;
