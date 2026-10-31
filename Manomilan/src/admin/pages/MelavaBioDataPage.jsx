import { useEffect, useState } from "react";
import { request } from "../../api";
import BioDataModal from "./Profiles/BioDataModal.jsx";
import "./melavaBioDataPage.css";

const getCachedProfile = (cacheKey, profileId) => {
  if (!cacheKey) return null;
  try {
    const cached = JSON.parse(sessionStorage.getItem(cacheKey) || "null");
    sessionStorage.removeItem(cacheKey);
    return cached && typeof cached === "object" && String(cached.id) === String(profileId)
      ? cached
      : null;
  } catch {
    sessionStorage.removeItem(cacheKey);
    return null;
  }
};

export default function MelavaBioDataPage({ params }) {
  const profileId = params?.id || "";
  const search = new URLSearchParams(window.location.search);
  const templateId = search.get("template") || "";
  const orientation = search.get("orientation") === "landscape" ? "landscape" : "portrait";
  const [profile, setProfile] = useState(() => getCachedProfile(search.get("cache_key"), profileId));
  const [error, setError] = useState("");

  useEffect(() => {
    if (profile) return undefined;
    let active = true;
    request(`/profiles/${profileId}/biodata-preview`)
      .then((response) => {
        if (!response?.data) throw new Error("The BioData preview was empty.");
        if (active) setProfile(response.data);
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || "Unable to load candidate biodata.");
      });

    return () => {
      active = false;
    };
  }, [profileId, profile]);

  const closePage = () => {
    if (window.opener) {
      window.close();
      return;
    }
    window.location.assign("/admin/melava");
  };

  if (error) {
    return (
      <main className="mbp-state">
        <section>
          <h1>Could not open BioData</h1>
          <p>{error}</p>
          <button type="button" onClick={closePage}>Back to Melava registrations</button>
        </section>
      </main>
    );
  }

  if (!profile) {
    return (
      <main className="mbp-state" aria-busy="true">
        <section><span className="mbp-spinner" /><p>Preparing BioData preview...</p></section>
      </main>
    );
  }

  return (
    <BioDataModal
      profile={profile}
      onClose={closePage}
      initialTemplateId={templateId}
      initialOrientation={orientation}
      fullScreen
    />
  );
}
