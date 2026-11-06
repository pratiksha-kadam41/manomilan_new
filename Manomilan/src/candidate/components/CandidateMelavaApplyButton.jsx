import { useEffect, useState } from "react";
import { CalendarDays } from "lucide-react";
import { request } from "../../api";

let activeCampaigns;
let activeCampaignsRequest;

function getActiveCampaigns() {
  if (activeCampaigns) return Promise.resolve(activeCampaigns);
  if (!activeCampaignsRequest) {
    activeCampaignsRequest = request("/melava-campaigns/active", { auth: false })
      .then((response) => {
        activeCampaigns = Array.isArray(response?.data) ? response.data : [];
        return activeCampaigns;
      })
      .catch((error) => {
        activeCampaignsRequest = null;
        throw error;
      });
  }
  return activeCampaignsRequest;
}

export default function CandidateMelavaApplyButton({ className = "" }) {
  const [campaign, setCampaign] = useState(null);

  useEffect(() => {
    let active = true;
    getActiveCampaigns()
      .then((campaigns) => {
        if (active) setCampaign(campaigns[0] || null);
      })
      .catch((error) => {
        console.error("Unable to load active Melava campaigns.", error);
      });

    return () => {
      active = false;
    };
  }, []);

  if (!campaign?.id) return null;

  return (
    <a
      className={`cnd-melava-apply-button ${className}`.trim()}
      href={`/melava-registration?campaign=${campaign.id}`}
    >
      <CalendarDays size={14} />
      Apply for Melava
    </a>
  );
}
