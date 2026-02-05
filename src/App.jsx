import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { ToastProvider } from './context/ToastContext';
import { ConfirmProvider } from './context/ConfirmContext';
import Login from './pages/Login';
import Register from './pages/Register';
import RegisterStaff from './pages/RegisterStaff';
import StaffDashboard from './pages/StaffDashboard';
import StaffUpload from './pages/StaffUpload';
import SupervisorDashboard from './pages/SupervisorDashboard';
import QualityCheck from './pages/QualityCheck';
import Report from './pages/Report';
import BulkImport from './pages/BulkImport';
import Layout from './components/Layout';
import CanvassingGroupList from './pages/CanvassingGroupList';
import CanvassingGroupForm from './pages/CanvassingGroupForm';
import CanvassingGroupDetail from './pages/CanvassingGroupDetail';
import MyCanvassingGroups from './pages/MyCanvassingGroups';
import CanvassingExecution from './pages/CanvassingExecution';
import OnlineCanvassing from './pages/OnlineCanvassing';
import SupervisorOnlineCanvassing from './pages/SupervisorOnlineCanvassing';

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
      {/* <Route path="/register" element={user ? <Navigate to="/dashboard" /> : <Register />} /> */}
      <Route path="/register-staff" element={user ? <Navigate to="/dashboard" /> : <RegisterStaff />} />

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

      <Route
        path="/report"
        element={
          <PrivateRoute requiredRole="supervisor">
            <Layout>
              <Report />
            </Layout>
          </PrivateRoute>
        }
      />

      <Route
        path="/bulk-import"
        element={
          <PrivateRoute>
            <Layout>
              <BulkImport />
            </Layout>
          </PrivateRoute>
        }
      />

      {/* Supervisor - Canvassing Groups */}
      <Route
        path="/canvassing-groups"
        element={
          <PrivateRoute requiredRole="supervisor">
            <Layout>
              <CanvassingGroupList />
            </Layout>
          </PrivateRoute>
        }
      />
      <Route
        path="/canvassing-groups/create"
        element={
          <PrivateRoute requiredRole="supervisor">
            <Layout>
              <CanvassingGroupForm />
            </Layout>
          </PrivateRoute>
        }
      />
      <Route
        path="/canvassing-groups/:id"
        element={
          <PrivateRoute requiredRole="supervisor">
            <Layout>
              <CanvassingGroupDetail />
            </Layout>
          </PrivateRoute>
        }
      />
      <Route
        path="/supervisor-online-canvassing"
        element={
          <PrivateRoute requiredRole="supervisor">
            <Layout>
              <SupervisorOnlineCanvassing />
            </Layout>
          </PrivateRoute>
        }
      />
      <Route
        path="/canvassing-groups/:id/edit"
        element={
          <PrivateRoute requiredRole="supervisor">
            <Layout>
              <CanvassingGroupForm />
            </Layout>
          </PrivateRoute>
        }
      />

      {/* Staff - Canvassing Groups */}
      <Route
        path="/my-canvassing-groups"
        element={
          <PrivateRoute requiredRole="staff">
            <Layout>
              <MyCanvassingGroups />
            </Layout>
          </PrivateRoute>
        }
      />
      <Route
        path="/canvassing-execution/:groupId"
        element={
          <PrivateRoute requiredRole="staff">
            <Layout>
              <CanvassingExecution />
            </Layout>
          </PrivateRoute>
        }
      />
      <Route
        path="/online-canvassing"
        element={
          <PrivateRoute requiredRole="staff">
            <Layout>
              <OnlineCanvassing />
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
      <ToastProvider>
        <ConfirmProvider>
          <AuthProvider>
            <AppRoutes />
          </AuthProvider>
        </ConfirmProvider>
      </ToastProvider>
    </Router>
  );
}

export default App;

