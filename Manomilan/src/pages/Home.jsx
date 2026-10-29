import Hero from "../components/Hero.jsx";
import WhyChoose from "../components/WhyChoose.jsx";
import Committee from "../components/Committee.jsx";
import Gallery from "../components/Gallery.jsx";
import UpcomingEvents from "../components/UpcomingEvents.jsx";

export default function Home() {
  return (
    <>
      <main>
        <Hero />
        <WhyChoose />
        <Committee />
        <Gallery />
      </main>
      <UpcomingEvents />
    </>
  );
}