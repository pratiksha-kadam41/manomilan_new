import AdminLayout from "../components/AdminLayout";
import StatCard from "../components/StatCard";
import { stats, genderData } from "../data/adminData";
import { Bar } from "react-chartjs-2";
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend,
} from "chart.js";

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

export default function Dashboard() {
  const today = new Date().toLocaleDateString("en-US", {
    weekday: "long", year: "numeric", month: "long", day: "numeric",
  });

  const chartData = {
    labels: ["Male Candidates", "Female Candidates"],
    datasets: [{
      label: "Number of Candidates",
      data: [genderData.male, genderData.female],
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

  const verifiedPct = Math.round((stats.verified / stats.totalProfiles) * 100);

  return (
    <AdminLayout currentPath="/admin">
      <div className="adm-banner">
        <h1>Admin Dashboard</h1>
        <p>{today}</p>
      </div>

      <div className="adm-cards">
        <StatCard value={stats.totalUsers} label="Total Users" color="#7c6df0" />
        <StatCard value={stats.totalProfiles} label="Total Profiles" color="#2f9bf0" />
        <StatCard value={stats.verified} label="Verified Profiles" color="#1ec99b" percent={verifiedPct} />
        <StatCard value={stats.unverified} label="Unverified Profiles" color="#e53e3e" percent={100 - verifiedPct} />
      </div>

      <div className="adm-chart-box">
        <h2>Candidate Distribution by Gender</h2>
        <div style={{ height: 360 }}>
          <Bar data={chartData} options={options} />
        </div>
      </div>
    </AdminLayout>
  );
}