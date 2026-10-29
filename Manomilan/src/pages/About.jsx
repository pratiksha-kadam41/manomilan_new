import { useState } from "react";

export default function About() {
  const [flipped, setFlipped] = useState(false);

  return (
    <main className="about-top">
      {/* ---------- Welcome ---------- */}
      <section id="about" className="gray-sec">
        <div className="site-container">
          <div className="page-title-wrap">
            <h2 className="page-title">WELCOME TO Aaryans Manomilan</h2>
          </div>

          <div className="two-col">
            <div className="media-box">
              <div className="customize-img">
                <img alt="customize" src="/website/about_us.jpg" />
              </div>
            </div>

            <div className="writeup-box">
              <p>
                At Aaryans Manomilan we believe that marriage is more than just a match—it's the beginning of a lifelong journey. Our platform is designed to connect individuals and families with genuine, like-minded partners based on shared values, culture, and preferences. With a secure and easy-to-use interface, we're committed to helping you find not just a partner, but a perfect match. Our approach blends cultural values with modern tools to deliver the most reliable and meaningful matches. With family involvement at the core, we aim to create connections that are deeply rooted and truly valued.
                <br /><br />
                This platform is the brainchild of Milind Ladge, who recognized the need for a matrimonial service that blends age-old traditions with modern convenience in a perticular community. The beautiful tagline—&quot;इथे मनं मिळतात... आणि नाती जुळतात…&quot;—and it perfectly captures the essence of his vision
                <br /><br />
                A key vision behind this initiative is to serve the Jain community—a close-knit and culturally rich group—by bringing its members even more closer and by this create a meaningful space where individuals and families can connect, communicate, and find the right life partners within the community.
                <br /><br />
                As we grow, we also aspire to extend this trusted approach to other communities, helping more individuals and families find the right match in a meaningful and respectful way. With tradition at heart and connection as our goal, we're here to help create not just matches, but lasting bonds.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ---------- From Owner's Desk ---------- */}
      <section id="ownerDesk" className="gray-sec">
        <div className="site-container">
          <div className="page-title-wrap">
            <h2 className="page-title">From Owner's Desk</h2>
          </div>

          <div className="two-col owner-grid">
            <div>
              <div className="flip-card" onClick={() => setFlipped((f) => !f)}>
                <div className={`flip-card-inner${flipped ? " is-flipped" : ""}`}>

                  {/* English side */}
                  <div className="flip-card-front">
                    <p><strong>Namaskar, Jay Jinendra!</strong></p>
                    <p>Myself Mr. Milind Ladge. I started different ventures of Aryans Group over the years. In all this, I have always felt that while doing something for myself, I should do something good for the society, for my people. So, considering our society and realizing the current need, we are starting a new initiative.</p>
                    <p>We are starting this activity, keeping in mind, the needs of young boys and girls, who want to get married and the current system of matchmaking. This activity is started with an intention of not only to match the pairs but in true sense, connecting their minds with each other. With this purpose, a new organization called ‘Manomilan’ is being started.</p>
                    <p>Ofcourse, this organization is not just for arranging marriages, but for true connections. The marriage ceremony will take place only if there is a direct connection of the minds. The basic purpose of starting this activity or this institution is to keep our society united and to preserve the Jain society, to preserve Jainism. This is going to be an effort on my part keeping in mind that we owe to the society we live in.</p>
                    <p>The best part is that I have enough space of my school premises. I wish all the boys and girls and their parents all the very best for their future endeavors of finding the best matches.</p>
                    <p>Thank you.<br /><strong>Jay Jinendra!</strong></p>
                    <p><small>(Click here to read in मराठी)</small></p>
                  </div>

                  {/* Marathi side */}
                  <div className="flip-card-back">
                    <p><strong>नमस्कार आणि जय जिनेन्द्र!</strong></p>
                    <p>मी मिलिंद लडगे, आर्यन्स ग्रुप च्या वेगवेगळ्या व्हेंचर्स ची सुरुवात माझ्याकडून गेल्या काही वर्षांमध्ये झाली. या सगळ्यामध्ये कायमच, माझ्यासाठी काहीतरी करता करता समाजासाठी, माझ्या लोकांसाठी काहीतरी चांगलं करावं असं कायमच वाटत आलंय. आणि त्यासाठीच आपल्या समाजाचा विचार करून आणि सध्याची गरज ओळखून एक नवीन उपक्रम सुरू करतोय.</p>
                    <p>हा उपक्रम म्हणजे त्यांचं एकमेकांशी ‘मनो-मिलन’ करण्याचा मानस आहे. याचसाठी मनोमिलन नावाची नवी संस्था सुरू करतोय. अर्थात नुसतंच लग्न जमावण्यासाठी ही संस्था नाही तर खऱ्या अर्थाने मनोमिलन करण्यासाठी आहे. म्हणजेच मनांचं मिलन झालं पाहिजे तरच लग्न सोहळा पार पडेल अशी यामागची भूमिका आहे.</p>
                    <p>हा उपक्रम किंवा ही संस्था सुरू करण्याचा मूळ उद्देश हा आपला समाज एकसंध ठेवणे आणि जैन समाज जपणे अर्थात Jainism जपणे हाच सर्वस्वी आहे. आपण ज्या समाजात राहतो त्या समाजाचं काहीतरी देणं लागतं हे लक्षात घेऊन माझ्या परीने हा एक प्रयत्न असणार आहे.</p>
                    <p>या सगळ्यात चांगली गोष्ट म्हणजे माझ्याकडे माझ्या शाळांमुळे पुरेशी जागा उपलब्ध आहे. पुण्यात जवळपास ९ ठिकाणी याशिवाय सोलापूर, बेळगाव, हुबळी या ठिकाणी शाळेच्या जागा उपलब्ध आहेत ज्याचा वापर मला या मुला-मुलींच्या भेटी-गाठी आणि मनमोकळ्या गप्पा यासाठी करायचं आहे जेणेकरून अगदी मोकळ्या वातावरणात ते एकत्र येऊन दिलखुलास बोलू शकतील.</p>
                    <p>मी सर्व मुला-मुलींना आणि पालकांना त्यांच्या पुढच्या वाटचालीसाठी शुभेच्छा देतो.<br /><strong>जय जिनेन्द्र !!!</strong></p>
                    <p><strong>- मिलिंद लडगे</strong><br />संस्थापक-संचालक, आर्यन्स ग्रुप</p>
                  </div>

                </div>
              </div>
            </div>

            <div className="customize-img">
              <img alt="customize" src="/website/owner_desk.jpg" />
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}