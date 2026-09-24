<?php
/**
 * trustee-candidates-data.php — survey wording and candidate responses.
 *
 * ── THIS IS THE ONLY FILE YOU EDIT TO PUBLISH RESPONSES ──────────────────────
 * trustee-candidates.php reads this and never needs changing to add, amend or
 * withdraw an answer.
 *
 * Nothing here is published until every question's `prompt` is filled in. While
 * any prompt is still empty the public page says responses are not yet
 * available and shows no questions, names or answers — so a half-filled file
 * cannot leak a placeholder onto a public page.
 *
 * Answers are printed VERBATIM. Paragraph breaks are blank lines; a line that
 * starts with "- " becomes a bullet. Nothing else is interpreted, and nothing
 * is corrected — spelling, grammar, punctuation and claims are the candidate's.
 */

// ── Page-level settings ──────────────────────────────────────────────────────

/** Shown as "Last updated" at the top of the page. Update when you change anything. */
const TC_LAST_UPDATED = '2026-09-24';  // working draft of this date

/** The candidate-organised PDF. Leave '' to hide the download button entirely. */
const TC_PDF_URL      = '';          // e.g. 'documents/BVTU-Trustee-Candidate-Responses-2026.pdf'

/** Survey deadline, and the date we last checked for late responses. */
const TC_DEADLINE     = '2026-09-26';
const TC_LAST_CHECKED = '2026-09-24';

/**
 * Set true ONLY after the deadline has passed AND you have checked again.
 * Until then a non-responding candidate reads "No response received as of
 * <last checked>", which is a statement about what we know rather than about
 * what they did.
 */
const TC_RECHECKED_AFTER_DEADLINE = false;

// ── Survey questions ─────────────────────────────────────────────────────────
//
// Paste the EXACT wording BVTU sent. The short labels below are only to help
// you match each slot to the right question — they are never displayed.
//
// 'id'     never change once responses reference it.
// 'label'  your own shorthand, for this file only.
// 'prompt' the exact question as sent.
// 'follow' an exact follow-up prompt shown beneath the main one, or null.

const TC_SURVEYS = [
    'new' => [
        'name'      => 'New candidates',
        'questions' => [
            ['id' => 'n1',  'label' => 'Why running',            'prompt' => "Why are you running for SD54 school trustee?", 'follow' => null],
            ['id' => 'n2',  'label' => 'Education partners',     'prompt' => "Who would you describe as \"key education partners\" in SD54, and do you think engaging with these partners regularly is important to the work of a school trustee?",
             // 2(a). Its own prompt. A candidate who answered 2 and 2(a) together
             // is handled by answering under the id 'n2+n2a' — see below.
             'follow' => null],
            ['id' => 'n2a', 'label' => 'Community voices',       'prompt' => "The BC School Trustees Association describes the role of trustees this way: trustees \"listen to their communities, guide the work of their school district\" (BCSTA, Trustee Responsibilities, bcsta.org/trustee-responsibilities). Do you agree that community voices should guide the work of the district, and how will you regularly engage with community voices to inform your work?", 'follow' => null],
            ['id' => 'n3',  'label' => 'Differing opinion',      'prompt' => "If you hold a different opinion than district staff or other board members, how might you raise your concerns while respecting the board's final decision?", 'follow' => null],
            ['id' => 'n4',  'label' => 'Live streaming',         'prompt' => "Would you vote in favour of live streaming board meetings? Why or why not", 'follow' => null],
            ['id' => 'n5',  'label' => 'Memory of an educator',  'prompt' => "Bonus: Please share a positive memory of when an educator made a difference in your life", 'follow' => null],
        ],
    ],
    'incumbent' => [
        'name'      => 'Incumbent trustees',
        'questions' => [
            ['id' => 'i1',  'label' => 'Terms / why again',      'prompt' => "How many 4-year terms have you been a trustee, and why are you running again?", 'follow' => null],
            ['id' => 'i2',  'label' => 'Classroom / changed mind / voted against', 'prompt' => "Please share a time when you did one or more of the following. You are welcome to respond to just one, or to as many as you like. (a) Visited a classroom to see what teaching and learning is like in SD54; (b) Heard another trustee's question and response during a Board meeting which caused you to change your mind; (c) Voted against a motion.", 'follow' => null],
            ['id' => 'i3',  'label' => 'Education partners',     'prompt' => "Who would you describe as \"key education partners\" in SD54, and do you think engaging with these partners regularly is important to the work of a school trustee?", 'follow' => null],
            ['id' => 'i3a', 'label' => 'Community voices',       'prompt' => "The BC School Trustees Association describes the role of trustees this way: trustees \"listen to their communities, guide the work of their school district\" (BCSTA, Trustee Responsibilities, bcsta.org/trustee-responsibilities). Do you agree that community voices should guide the work of the district, and how have you regularly engaged with community voices to inform your work?", 'follow' => null],
            ['id' => 'i4',  'label' => 'Live streaming',         'prompt' => "Would you vote in favour of live streaming board meetings? Why or why not?", 'follow' => null],
        ],
    ],
];

/**
 * Combined answers.
 *
 * If a candidate gave ONE answer covering both the partners question and its
 * community-voices follow-up, put that answer under the combined id and the
 * page shows both prompts together above their single answer. Their words are
 * neither duplicated nor split.
 *
 *   'answers' => [ 'n2+n2a' => "their one answer" ]
 */
const TC_COMBINED = [
    'n2+n2a' => ['n2', 'n2a'],
    'i3+i3a' => ['i3', 'i3a'],
];

// ── Candidates ───────────────────────────────────────────────────────────────
//
// 'slug'   used in the URL (#candidate-jane-smith). Never change it once shared.
// 'name'   as it appears on the ballot.
// 'group'  'new' or 'incumbent' — decides which survey they are shown against.
// 'status' 'responded' | 'no_response' | 'declined'
// 'answers' keyed by question id. Omit a question, or leave it '', and the page
//           says "No answer provided to this question." — which is different
//           from not responding at all.
// 'updated' optional 'YYYY-MM-DD' shown as a dated note when an answer is added
//           or changed after first publication.
//
// Order does not matter: the page sorts alphabetically by surname within group.

const TC_CANDIDATES = [

    [
        'slug'    => 'diane-mackay',
        'name'    => 'Diane Mackay',
        'group'   => 'new',
        'status'  => 'responded',
        'answers' => [
            'n1'       => "I have been interested in the school trustee position since the 1990's when my children were in elementary school. My life was busy as I worked full-time and was raising 7 children from a blended family. At one point I had children in day care, at Muheim Elementary, Chandler Park Middle School and SSS. Now that I am retired and have grandchildren in local schools, I felt I could finally commit the time and effort. Two priorities that have been constant in my life are children and education. Most of my experiences as a parent have been positive - teachers have been supportive, principals have been fair and everyone worked together to move my children through school. My belief is that the students in school now are the future of our community and the best way we can prepare them is to provide the best education in a supportive environment.",
            'n2'       => "Some of the partnerships would be the district schools, and within those the local Parents Advisory Councils, as well as the Ministry of Education and Child Care and the Child Development Centre. Another important partnership would be the BV Teachers Union. Future partnerships could include the Child Development Centre, to stay current on educational practices and support resources.",
            'n2a'      => "Community voices are key to knowing what is happening in the schools and what is important for school boards to focus on in regards to the strategic vision. I believe trustees should meet with local PAC's, attend school functions as representatives of the board, be involved with some of the committees that fall under the Ministry of Education and Child Care (Indigenous learning, for example), and cultivate relationships with the Child Development Centre. The teachers union could be the contact point for getting information to the teachers, but also hearing from them what is happening in the classrooms and the challenges they face.",
            'n3'       => "Having been on many committees, boards, and working groups over my career - I have observed that some of the best results come from having different views and opinions. It allows the healthy discussion and debate required to fully understand problems and solutions before decisions are reached. Once a decision is reached, all members should be supportive of implementing the decision.",
            'n4'       => "Not having served on the school board before, I am unsure of how I would vote in the matter of live streaming meetings. I would assume that some discussions would be private, but most could be public. I have watched the Town of Smithers meetings and appreciate that opportunity. One thing I thought was a great idea was when the local newspaper published the votes by each councilor and their reasons why. This gave great insight into issues and initiatives, and provided some transparency to the public.",
            'n5'       => "One particular educator that I would say had a lasting impact on my life was Mrs. Dorothy Wandler, my teacher in grade 4 and 5. Part of this was due to her encouragement, guidance and praise when we were just learning how to write reports. She made me want to strive towards excellence in all my educational endeavours. Another area where she differed from other teachers of the day (1970's) was in how she corrected behaviour in the classroom without shaming. I believe that impacted how I parented later in life, and also how I treated disciplinary measures with staff over the years.",
        ],
    ],
    [
        'slug'    => 'rod-taylor',
        'name'    => 'Rod Taylor',
        'group'   => 'new',
        'status'  => 'responded',
        'answers' => [
            'n1'       => "The education of our young people is one of the most important responsibilities we have as a society. Young people today—who will be the pillars of society in only a few short years—deserve the best tools and training with which to succeed. I want to see them properly equipped to understand their world and to take their places in it.",
            'n2'       => "Students and their parents are at the centre of the education partnership. Teachers, of course, are the trusted partners with whom parents and students should have a positive relationship. Teachers, in turn, need a supportive administration team and that ultimately involves the board of trustees and other members of the community. Of course, many students are also instructed and influenced in their local churches / spiritual communities and in the clubs or teams to which they may belong.",
            'n2a'      => "Yes, I intend to be available to hear from the community and especially from parents. Nobody has a higher stake in the success and wellbeing of the children than their own parents.",
            'n3'       => "It’s likely that there will be differences of opinion. That’s why we elect boards or groups of representatives rather than just one person. We learn from each other and the goal is to find good solutions through the sharing of ideas and perspectives. It is my goal to communicate my thoughts and perspectives in a respectful manner.",
            'n4'       => "I think so; I’d want to listen to the views of other board members first. I do believe in transparency so I don’t see any reason not to make the meetings accessible to the public.",
            'n5'       => "I’ve been privileged to have a number of good educators / mentors in my life. I still do. My first educators, of course, were my Mom and Dad who both had a lasting and positive impact on my life. I also remember particularly my grade 2 teacher, my grade 6 teacher and my grade 7 teacher. My grade 6 teacher really boosted my confidence and encouraged me. There’s no doubt that a teacher can inspire, motivate and encourage a young person to learn and to achieve great things.",
        ],
    ],
    [
        'slug'    => 'casda-thomas',
        'name'    => 'Casda Thomas',
        'group'   => 'new',
        'status'  => 'responded',
        'answers' => [
            'n1'       => "With two kids attending Muheim Elementary School, a heart for my community and experience in elected office, running for school trustee feels like an obvious choice for me.\n\nMy kids are involved in many sports and arts activities and over the years I have become actively involved in their activities, coaching both softball and hockey. This has grown my connection to the community even more by allowing me to meet all sorts of great kids and parents that I only had the opportunity to meet through coaching.\n\nMore formally, I have always valued democracy and civic engagement. I have been involved in various ways over the years. Early in my time in Smithers, I spent several years on the Town’s Advisory Planning Commission. I was then elected to Smithers Town Council and served in that capacity from 2018-2022. At that time, I chose not to run for a second term due to family commitments, but my commitment to civic engagement has remained and now I am ready to represent my community at the school board table.",
            'n2'       => "I strongly believe in engaging with partners and the public. My current role with the BC Wildfire Service is in community engagement and I have previously served a term on Smithers town council where I highly valued engagement with partners and community members.\n\nKey educational partners for the school district would include:\n\n- Teachers and school counselors,\n\n- Educational assistants and other school-level staff,\n\n- School-level leadership,\n\n- Students, student groups and student body representatives,\n\n- Parents, guardians and parent groups, such as the local PACs.\n\nOther community members also play vital roles in guiding and supporting youth and may also be defined as partners including, for example, community organizations, coaches and instructors, to name just a few.",
            'n2a'      => "I believe that engaging with the community is a core part of an elected official’s role. I don’t see myself as an expert or someone who inherently has all the answers, but rather as a conduit between the community and the school district.\n\nI commit to being transparent about decision making, and available for dialogue with partners and community members.",
            'n3'       => "Having a group of trustees that can hold and discuss different opinions is good; it’s how we arrive at the best decisions possible. And fostering a culture of curiosity and respectful debate is healthy.\n\nWhen I served on Smithers town council, I regularly asked questions, voiced opinions and listened to what other councillors had to say. Sometimes what I said influenced our direction, other times something that another person said caused me to reconsider my position.\n\nI commit to sharing my perspective with the other trustees at the board table and to listening to their opinions with an open mind. This does not mean compromising my values. It means being open to different ways of arriving at the best possible outcomes.",
            'n4'       => "Yes, I would be in favour of live streaming board meetings.\n\nThis was the practice when I was on council and it was an excellent way for residents to engage at a level that worked for them. Few people have the time in their lives to attend board meetings but making them available online makes the meetings so much more accessible.",
            'n5'       => "Mrs. Forrest, my grade seven homeroom and English teacher. There’s not one big thing that she did that sticks out for me, rather it was many small things that created a secure foundation for my first year in middle school. I don’t even think I realized or appreciated it at the time, but she is the teacher that consistently comes to mind when I think about my school years.\n\nShe was strict, but also kind and calm. She asked us questions, respected our autonomy, and taught us about ‘real world things’ like personal wellbeing and compounding interest (this was not normal at the time!) If a student had menstrual cramps, they were free to leave the class and take care of themselves, and she would offer help discreetly, but without shame.\n\nShe wasn’t everyone’s favourite teacher, it was quieter than that, she modeled stability, students respected her. She somehow created a safe space for all of us to just settle, be ourselves and let go of the constant pressure to fit in.",
        ],
    ],
    [
        'slug'    => 'kim-ungers',
        'name'    => 'Kim Ungers',
        'group'   => 'new',
        'status'  => 'responded',
        'answers' => [
            'n1'       => "I am running for Zone 3 (Telkwa/ Quick) school trustee because I care about our public schools and believe every child has the right to a quality education in a safe/ supported environment from the moment they get on the school bus/ are dropped off at the school to the end of the school day. I feel I would make a great trustee because of my background in education and my connections with the schools and community. I am friendly, organized, compassionate, and always strive to think outside the box for collaborative solutions. Also, this opportunity comes at a time when I am working part time and my kids are at the age where they are a bit more independent and I can dedicate the time that the role of school trustee deserves.",
            'n2+n2a'   => "Key education partners in the community are of course the staff and families of the students who attend our public schools. There are also various businesses and organizations with which the board could connect and collaborate, including local government, libraries, museums, community services, First Nations, sports clubs, special interest groups, farms, and emergency services to name a few. If elected as trustee, I would encourage community members to reach out to me with concerns and ideas to bring to the board. People who know me know that I love making connections!",
            'n3'       => "If I had the honour to have a seat at the board table, I would express my opinion even if it differed from the district staff’s opinions or another board member’s. Afterall, the purpose of the board having 7 members is so that no one person is making important decisions about the future of the school district and individual student needs. I realize that these conversations won’t always be comfortable, but I believe we can work collaboratively to reach a consensus that fits with the board values and has students’ best interests in mind. Once that decision is made, I would respect it knowing that my voice was heard along with 6 other voices and the communities we represent.",
            'n4'       => "Board meetings are already open to the public so live streaming would simply make them more accessible, which I think is important considering the size of the district, winter road conditions, and the fact that many people who may want to attend them are parents who could then live stream from home without needing to arrange childcare. I do think it warrants a discussion on whether the live stream would be open to the whole world or whether there would be a way to allow only district residents to access the live stream.",
            'n5'       => "I have so many positive memories of teachers, but I would have to say my high school French and Spanish teachers had a big impact in my life as my passion for languages and cultures started in their classes. They encouraged me to participate in a 2-month exchange to Quebec in grade 10 and a year-long exchange to Ecuador after graduating. I then went on to attend university in Japan and South Korea and also volunteered in Brazil, learning a few more languages along the way. Now, I share that passion with my adult EAL learners in my role as Language Instructor/ Assessor at Smithers Community Services Association.",
        ],
    ],
    [
        'slug'    => 'matt-williamson',
        'name'    => 'Matt Williamson',
        'group'   => 'new',
        'status'  => 'responded',
        'answers' => [
            'n1'       => "I am running as a candidate for school trustee for a few different reasons.\n\n- Education is important to me, I believe that it is the foundation for building a successful life.\n\n- I’d like to play a role in helping increase scholastic achievement in our district.\n\n- I feel like the district and the board need to be more transparent and engaged with parents.\n\n- On a more personal level, I have children who are or will soon be in the public school system and I am married to an educator, therefore, the quality of the environment which they learn and teach in is important to me.",
            'n2'       => "I think that key education partners would first and foremost be the teachers and other district staff who are actually employed in the schools. They are the front line workers in the education system and should have the most encompassing view of the needs of the student body as a whole. Next, would be the parents of the students. I think that parents should have a proactive role in the education of their children in a way that compliments and encourages learning at school. I think that engaging with partners is very important, and I would commit to visiting schools and talking with staff and parents often.",
            'n2a'      => "I believe that the voice of the community should carry weight. If people are willing to have open and honest conversations about the education of their children, then that should be a priority and I would really like to see a lot of parental engagement with the board. I think that as trustees, we should not rely solely on things like studies from the provincial government, etc. British Columbia is a vast province with a very diverse population, so what works for a district on Vancouver Island or the Lower Mainland may not necessarily work for our small northern district and the people we serve. Our demographics are different and our logistics are more challenging. For example on logistics, in Vancouver it is only a matter of hopping on public transit for a Science World field trip during school hours, whereas in the north, we don’t have those transportation and destination options without considerable effort from staff and parent committees (not to mention the bussing issues we face here).",
            'n3'       => "I tend to try to base my opinions on facts if I can. I would make my case and have the data to back it up. If I had an uninformed opinion, I would first set myself on the task of becoming informed. In the end, if the board made a contrary final decision, I would be required to respect that decision by the rules of the code of conduct for trustees. However, if I believed it was worthwhile, I would revisit the topic as much as practicable.",
            'n4'       => "Yes, I think that in this day and age people shouldn’t have to drive to the board meeting to observe it. Parents could then more conveniently stay up to date with what the board is doing, which should lead to more engagement. This would also allow people who may not have the means to attend in person the ability to observe. It would also overcome weather related travel concerns between communities. Coincidentally, this could also have a beneficial environmental impact as well if more people watched from home.",
            'n5'       => "There have been so many wonderful educators in my life, each contributing their particular block in the foundation on which I’ve built my life. One of many experiences that I could share would be the time that I was introduced to computers and programming by my math teacher Mrs. Meutzner. This was in a time before the internet (gasp!). Coding was not taught in school at the time, but she encouraged me to learn how to write programs in a couple of different programming languages and provided me with some resources that weren’t available in schools yet. My 12 year old brain could hardly fathom the vast universe of possibilities that lay before me with the new found skills to command a computer! While we take these sorts of things for granted these days, back then it lit a spark that turned into a lifelong interest in computers and electronics. This in turn guided my choices in post secondary education and led me to amateur radio which was the basis of all the emergency communication volunteerism that I continue to pursue to this day!",
        ],
    ],
    [
        'slug'    => 'frank-farrell',
        'name'    => 'Frank Farrell',
        'group'   => 'incumbent',
        'status'  => 'responded',
        'answers' => [
            'i1'       => "Thank you for the opportunity to answer these important questions that your membership can see.\n\nI have served 2 - 3 year terms and 3-4 year terms. My reasons for running for another term is to offer an experienced voice during a very important juncture in education in this province and district. There are serious concerns about funding and the cost to educate our students with our increasing inflationary times.Moreover, it seems that advocacy in ensuring students, all students , are in access to a safe inclusive and relevant education experience that includes the core competencies . Finally, with such emerging technologies as AI I offer my name as an experienced voice to lobby and steward an education system that is supportive of students' need to adapt to a future working world that may be far different that is existing today.",
            'i2'       => "(a) Normally, I don't visit while in session. I feel that infringes on student learning of the curriculum of the class. I visited a classroom to read a book to an elementary school class as a part of a special event.Moreover , I have had numerous visits to schools by invitation during special events like Pie night at Walnut Park, Breakfast and Books at Muheim, along with Pride day at Smithers Secondary. I also attend regular PAC meetings at Muheim and Walnut Park.\n\n(b) Firstly, I would like to say that there have been numerous times where Trustees have offered me insight that I have never envisioned. That is a great part of a well balanced board of education. Different backgrounds representing the community as a whole. Specifically, I have been amazed by the insight that has attained through Indigenous learning gained through listening to Trustee Michell.\n\n(c) one particular time I voted against a motion is when I forwarded a motion to refuse a percentage hike in the Trustee Stipend. My thought was that no pay raise should be accepted unless all employees at the District have received a comparable or higher raise",
            'i3'       => "Firstly, our union partners BVTU and CUPE. Our Principals and Vice-Principals , and our exempt staff. In addition our stakeholder groups the community including parents through public consultation and participation at PAC meetings. Moreover, Provincially, we engage organizations as the BC School Trustees Association BCPublic School Employers Association. Employers. Finally we engage with students by attending invited events .",
            'i4'       => "Yes if it is cost effective and doesn't take money away from student learning.",
        ],
    ],
    [
        'slug'    => 'christina-graham',
        'name'    => 'Christina Graham',
        'group'   => 'incumbent',
        'status'  => 'no_response',
    ],
    [
        'slug'    => 'priscilla-michell',
        'name'    => 'Priscilla Michell',
        'group'   => 'incumbent',
        'status'  => 'no_response',
    ],
    [
        'slug'    => 'jennifer-smith',
        'name'    => 'Jennifer Smith',
        'group'   => 'incumbent',
        'status'  => 'no_response',
    ],

    /* ── PASTE CANDIDATES BELOW. Template: ───────────────────────────────────
    [
        'slug'    => 'jane-smith',
        'name'    => 'Jane Smith',
        'group'   => 'new',
        'status'  => 'responded',
        'updated' => null,
        'answers' => [
            'n1'  => "First paragraph exactly as written.\n\nSecond paragraph.\n\n- a bullet\n- another bullet",
            'n2'  => "",
            'n2a' => "",
            'n3'  => "",
            'n4'  => "",
            'n5'  => "",
        ],
    ],
    [
        'slug'   => 'alex-doe',
        'name'   => 'Alex Doe',
        'group'  => 'incumbent',
        'status' => 'responded',
        'answers' => [
            'i1'     => "",
            'i2'     => "",
            'i3+i3a' => "One answer that covers both the partners question and the community-voices follow-up.",
            'i4'     => "",
        ],
    ],
    [
        'slug'   => 'sam-jones',
        'name'   => 'Sam Jones',
        'group'  => 'new',
        'status' => 'no_response',
    ],
    [
        'slug'   => 'pat-lee',
        'name'   => 'Pat Lee',
        'group'  => 'new',
        'status' => 'declined',
    ],
    ─────────────────────────────────────────────────────────────────────────── */

];
