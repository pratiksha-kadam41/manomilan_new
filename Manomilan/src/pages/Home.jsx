import Hero from "../components/Hero.jsx";
import MelavaBanner from "../components/MelavaBanner.jsx";
import WhyChoose from "../components/WhyChoose.jsx";
import Committee from "../components/Committee.jsx";
import Gallery from "../components/Gallery.jsx";
import DowntimeNotice from "../website/DowntimeNotice.jsx";
import WebsiteUpcomingEvents from "../website/WebsiteUpcomingEvents.jsx";

export default function Home() {
  return (
    <>
      <DowntimeNotice placement="home" />
      <main>
        <Hero />
        <MelavaBanner />
        <WhyChoose />
        <Committee />
        <Gallery />
      </main>
      <WebsiteUpcomingEvents />
    </>
  );
}