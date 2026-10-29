import { useEffect, useState } from "react";
import AdminLayout from "../components/AdminLayout";
import StatCard from "../components/StatCard";
import { getAdminDashboard } from "../../auth";
import { Bar } from "react-chartjs-2";
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend,
} from "chart.js";

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

export default function Dashboard() {
  const [stats, setStats] = useState(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    getAdminDashboard()
      .then((data) => {
        if (active) setStats(data);
      })
      .catch((err) => {
        if (active) setError(err.message || "Could not load dashboard data.");
      });

    return () => {
      active = false;
    };
  }, []);

  const today = new Date().toLocaleDateString("en-US", {
    weekday: "long", year: "numeric", month: "long", day: "numeric",
  });

  const chartData = {
    labels: ["Total Users", "Total Profiles", "Verified Profiles"],
    datasets: [{
      label: "Count",
      data: [
        Number(stats?.total_users || 0),
        totalProfiles,
        verifiedProfiles,
      ],
      backgroundColor: ["rgba(54,162,235,0.7)", "rgba(255,99,132,0.7)"],
      borderColor: ["#2196f3", "#ff4d79"],
      borderWidth: 1,
      borderRadius: 8,
    }],
  };

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
  };

  const totalProfiles = Number(stats?.total_profiles || 0);
  const verifiedProfiles = Number(stats?.verified_profiles || 0);
  const unverifiedProfiles = Math.max(totalProfiles - verifiedProfiles, 0);
  const verifiedPct = totalProfiles
    ? Math.round((verifiedProfiles / totalProfiles) * 100)
    : 0;

  return (
    <AdminLayout currentPath="/admin">
      <div className="adm-banner">
        <h1>Admin Dashboard</h1>
        <p>{today}</p>
      </div>

      {error && <p role="alert">{error}</p>}

      <div className="adm-cards">
        <StatCard value={stats ? stats.total_users : "—"} label="Total Users" color="#7c6df0" />
        <StatCard value={stats ? totalProfiles : "—"} label="Total Profiles" color="#2f9bf0" />
        <StatCard value={stats ? verifiedProfiles : "—"} label="Verified Profiles" color="#1ec99b" percent={stats ? verifiedPct : 0} />
        <StatCard value={stats ? unverifiedProfiles : "—"} label="Unverified Profiles" color="#e53e3e" percent={stats ? 100 - verifiedPct : 0} />
      </div>

      <div className="adm-chart-box">
        <h2>User and Profile Overview</h2>
        <div style={{ height: 360 }}>
          {stats ? (
            <Bar data={chartData} options={options} />
          ) : (
            <p>{error || "Loading dashboard data..."}</p>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}