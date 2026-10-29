import { useState } from "react";
import { Heart, Check, MapPin, Briefcase, GraduationCap } from "lucide-react";

export default function MatchCard({ match }) {
  const [sent, setSent] = useState(false);

  return (
    <div className="cnd-match">
      <div className="cnd-match-avatar">{match.name.charAt(0)}</div>
      <h3>{match.name}</h3>
      <p className="cnd-match-age">{match.age} yrs</p>

      <ul>
        <li><MapPin size={15} /> {match.city}</li>
        <li><Briefcase size={15} /> {match.occupation}</li>
        <li><GraduationCap size={15} /> {match.education}</li>
      </ul>

      <button
        className={`cnd-match-btn ${sent ? "sent" : ""}`}
        onClick={() => setSent(true)}
        disabled={sent}
      >
        {sent ? <><Check size={16} /> Interest Sent</> : <><Heart size={16} /> Send Interest</>}
      </button>
    </div>
  );
}