import { useEffect, useState } from "react";
import { request } from "../api";

export default function MelavaBanner() {
  const [campaigns, setCampaigns] = useState([]);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    request("/melava-campaigns/active", { auth: false })
      .then((response) => {
        if (active) setCampaigns(Array.isArray(response?.data) ? response.data : []);
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || "Unable to load current events.");
      });

    return () => {
      active = false;
    };
  }, []);

  if (error || campaigns.length === 0) return null;

  return (
    <section className="melava-banner">
      <div className="site-container">
        <div className="melava-banner-inner">
          {campaigns.map((campaign) => (
            <article className="melava-event-banner" key={campaign.id}>
              <div>
                <span className="melava-banner-kicker">{campaign.title}</span>
                <h2>Registrations are open</h2>
                <p>
                  {campaign.description
                    || "Create your candidate profile and join this community matching event. Existing members can restore saved details with their mobile number."}
                </p>
              </div>
              <div className="melava-banner-actions">
                <a
                  href={`/melava-registration?campaign=${campaign.id}`}
                  className="btn melava-banner-btn"
                >
                  Register for this Melava <i className="bi bi-arrow-right" />
                </a>
                <span>
                  Fee {new Intl.NumberFormat("en-IN", {
                    style: "currency",
                    currency: campaign.currency,
                    maximumFractionDigits: 0,
                  }).format(Number(campaign.fee) + (campaign.booklet_available ? Number(campaign.booklet_fee) : 0))}
                  {campaign.booklet_available ? " (with booklet)" : ""}
                </span>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
