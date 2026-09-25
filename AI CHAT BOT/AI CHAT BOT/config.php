<?php
// AI Chatbot Configuration
//
// SECURITY NOTE: Never hardcode API keys in source files that may be committed
// to version control or shared. This key should be treated as compromised —
// regenerate it in Google AI Studio and set it as an environment variable
// (e.g. in your php.ini, .htaccess, or system env) named GEMINI_API_KEY.
$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
   require_once $localConfigFile;
}
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: (defined('LOCAL_GEMINI_API_KEY') ? LOCAL_GEMINI_API_KEY : ''));
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent');
define('GEMINI_MODEL', 'gemini-1.5-flash');

// Special marker the model must return verbatim (and only this) when the
// answer is not covered by the knowledge base below. api.php looks for this
// exact string so it can safely fall back to a "not in knowledge base" reply
// with suggested sample questions, instead of letting the model guess.
define('NOT_IN_KB_MARKER', '[[NOT_IN_KB]]');

define('SYSTEM_PROMPT', <<<EOT
You are an AI assistant for PASS College's student support system. Answer clearly, accurately, professionally, and friendly.
- Use ONLY the provided knowledge base below to answer. Do not use outside knowledge, and do not invent or guess details that are not explicitly present in the knowledge base.
- If the knowledge base does not contain enough information to answer the user's question, reply with EXACTLY this token and nothing else: [[NOT_IN_KB]]
- Do not apologize, do not explain why, do not add any other text when the answer is not in the knowledge base — just output the token above so the system can offer sample questions instead.
- If the question is a simple greeting or small talk, respond briefly and warmly without needing the knowledge base.
- Prefer concise actionable guidance over long paragraphs.
- When possible, include the relevant module name and a direct next step.

Supported modules are: Library, Clinic, Scholarship, Guidance, SSC, and SSAA.
EOT);

// Service context for PASS College Support System
// This context includes full module definitions, step-by-step usage, and FAQs.

define('SERVICE_CONTEXT', <<<EOT
You are an AI assistant for PASS College's student support system. Act as a professional, concise, friendly, and accurate assistant.

SYSTEM ROLE:
- Support students and teachers using PASS College modules.
- Treat each service module as separate: Library, Clinic, Scholarship, Guidance, SSC, SSAA.
- Do not assume cross-module access or data sharing.
- If a question is outside these modules, ask the user which service they need.
- Use a professional tone while keeping responses concise and friendly.

SERVICE MODULES:

Library Module:
- Manages book inventory, digital catalog search, reservations, and QR-code check-ins.
- Students can reserve copies, check availability, and use QR check-in at the library.
- Teachers and students can view lending status and reservation history.
- This module is separate from Clinic, Guidance, Scholarship, SSC, and SSAA.

Clinic Module:
- Tracks health records, daily health metrics, and clinic visit logs.
- Supports water intake, sleep tracking, symptoms, and general wellness entries.
- Clinic records are private and separate from Guidance counseling.
- Students can request clinic visits and review visit summaries.

Scholarship Module:
- Uses a checklist-based workflow for physical document submissions only.
- Supports TDP and TES scholarship application tracking.
- Does NOT accept digital uploads.
- Tracks checklist completion and document verification status.

Guidance Module:
- Handles counseling appointment requests, incident reports, and guidance cases.
- Manages reporter information, reported persons, and case details.
- Separate from Clinic health records and Scholarship documents.
- Students can submit concerns and follow up with counselors.

SSC Module:
- Manages student council events, candidate registration, and student activities.
- Supports event schedules, announcements, and participation tracking.
- Helps students engage in campus leadership and activities.

SSAA Module:
- Tracks student progression, alumni status, and employment updates.
- Supports graduate career information and alumni lifecycle updates.
- Separate from active student service modules.

STEP-BY-STEP GUIDES:

Library:
1. Open the Library module from your dashboard.
2. Search the catalog by title, author, or subject.
3. Choose an available copy and submit a reservation.
4. Confirm the reservation and note the pickup or loan details.
5. Use the QR-code check-in when you visit the library.

Clinic:
1. Open the Clinic module from your dashboard.
2. Enter your daily health metrics, such as water, sleep, and symptoms.
3. Review your health log entries and clinic notes.
4. Request a clinic visit if you need medical attention.
5. Confirm the visit log after your consultation.

Scholarship:
1. Open the Scholarship module from your dashboard.
2. Review the checklist for required physical documents.
3. Prepare the listed documents for TDP or TES submissions.
4. Submit the documents in person to the scholarship office.
5. Track your checklist status and wait for verification.

Guidance:
1. Open the Guidance module from your dashboard.
2. Choose Counseling Appointment or Incident Report.
3. Provide details about your concern and any involved persons.
4. Submit the request and wait for counselor response.
5. Monitor your case status and follow the counselor's advice.

SSC:
1. Open the SSC module from your dashboard.
2. Browse student council events and activities.
3. Register as a candidate or sign up for an event.
4. Submit required participation or campaign details.
5. Check event status and SSC announcements.

SSAA:
1. Open the SSAA module from your dashboard.
2. Review your student progression or alumni profile.
3. Update your employment or graduate status.
4. Submit any required career or alumni information.
5. Monitor updates and alumni support resources.

FAQ LIST:

Library FAQs:
1. How do I borrow a book?
2. How do I reserve a library book?
3. How do I search the digital catalog?
4. Can I reserve a book online?
5. How do I check in with a QR code?
6. What happens if my reserved book is unavailable?
7. How do I view my current loans?
8. How long can I borrow a book?
9. How do I cancel a reservation?
10. Can teachers reserve books too?
11. How do I see due dates?
12. Where can I find book availability?
13. Can I request a book that is not listed?
14. How do I report a missing book?
15. Is the catalog updated in real time?
16. How do I print or save my reservation?
17. What if my library QR code does not work?
18. How do I extend my borrowing period?
19. How can I access library reports?
20. How do I check if a book is available?

Clinic FAQs:
1. How do I log daily health data?
2. What health metrics can I track?
3. How do I record water intake?
4. How do I update my sleep log?
5. How do I request a clinic visit?
6. Can I review my clinic visit history?
7. Is my health data private?
8. How do I see my health record summary?
9. What should I do if I feel unwell?
10. How do I report symptoms?
11. Can teachers view clinic records?
12. How do I update my medication information?
13. How do I access clinic announcements?
14. What counts as a clinic visit log?
15. How do I change a scheduled appointment?
16. How do I submit a health update?
17. How do I check my clinic request status?
18. Can I enter multiple daily health logs?
19. How do I use the clinic module correctly?
20. How do I find my clinic notes?

Scholarship FAQs:
1. How do I track my scholarship status?
2. What documents are required for TDP?
3. What documents are required for TES?
4. How do I submit scholarship documents?
5. Can I upload documents online?
6. Is this system for physical documents only?
7. How do I check checklist progress?
8. How do I know if my documents are complete?
9. Who can I contact about scholarships?
10. How do I update my scholarship profile?
11. How do I view scholarship announcements?
12. What happens after I submit documents?
13. How do I fix a missing checklist item?
14. Can teachers help with scholarship status?
15. How do I confirm document receipt?
16. What if a document is rejected?
17. How do I find scholarship deadlines?
18. Can I change my scholarship type?
19. How do I know if I am eligible?
20. Does the system store my documents?

Guidance FAQs:
1. How do I request a counseling appointment?
2. How do I file an incident report?
3. How do I create a guidance case?
4. What information is needed for a report?
5. How do I update case details?
6. How do I check my case status?
7. Can teachers submit reports for students?
8. How do I add reporter information?
9. How do I attach supporting details?
10. How do I schedule a follow-up?
11. Is guidance data confidential?
12. How do I cancel an appointment?
13. How do I change an appointment request?
14. How do I contact a counselor?
15. How do I add a reported person?
16. How do I review past cases?
17. How do I access guidance announcements?
18. How do I report a non-urgent issue?
19. How do I escalate a concern?
20. What should I do after submission?

SSC FAQs:
1. How do I join an SSC event?
2. How do I register as a candidate?
3. How do I view student activities?
4. How do I check event schedules?
5. How do I sign up for an event?
6. How do I get event updates?
7. How do I submit an activity proposal?
8. How do I view campaign details?
9. Can teachers manage SSC events?
10. How do I join a council meeting?
11. How do I check election status?
12. How do I find volunteer opportunities?
13. How do I see event participants?
14. How do I request SSC support?
15. How do I update my candidate profile?
16. How do I check activity approval?
17. How do I view SSC announcements?
18. How do I cancel event registration?
19. How do I give feedback on an event?
20. How do I learn about leadership programs?

SSAA FAQs:
1. How do I update alumni employment status?
2. How do I see my progression details?
3. How do I check graduation tracking?
4. How do I update career information?
5. Can I access SSAA after graduation?
6. How do I add employer details?
7. How do I view alumni resources?
8. How do I submit job updates?
9. How do I check progression milestones?
10. How do I connect with alumni services?
11. How do I report employment changes?
12. How do I find alumni announcements?
13. Can teachers access SSAA data?
14. How do I view alumni profiles?
15. How do I verify graduation records?
16. How do I update personal status?
17. How do I request alumni support?
18. How do I share success stories?
19. How do I find alumni events?
20. How do I track long-term support?

FAQ ANSWERS AND FOLLOW-UPS:

Library FAQ Answers:
1. How do I borrow a book? To borrow a book from PASS College Library:

   • Log into your student account and open the Library module from your dashboard
   • Use the search bar to find books by title, author, or subject
   • Check the availability status (green = available, yellow = reserved, red = checked out)
   • Click "Reserve" on an available book and select your preferred pickup date
   • Confirm your reservation and note the pickup instructions

   You'll receive email confirmation with your reservation details and pickup location.
2. How do I reserve a library book? Here's how to reserve a book:

   1. Open the Library module from your dashboard
   2. Search for your desired book using the catalog
   3. Click the "Reserve" button on an available copy
   4. Choose your pickup date (typically within 3-7 days)
   5. Review and confirm your reservation details

   Your reservation will be held for 24 hours at the designated pickup location.
3. How do I search the digital catalog? To search our digital catalog:

   • Access the Library module and locate the search bar at the top
   • Enter your search terms (title, author, subject, or ISBN)
   • Use filters to narrow results by availability, publication year, or book type
   • Click on any result to view detailed book information
   • Check real-time availability and location details

   The catalog updates instantly as books are checked in or out.
4. Can I reserve a book online? Yes! Our Library module supports full online reservations:

   ✓ Search and browse the catalog from anywhere
   ✓ Check real-time book availability
   ✓ Submit reservation requests instantly
   ✓ Receive email notifications for pickup and due dates
   ✓ Manage all reservations from your dashboard

   No need to visit the library just to make a reservation.
5. How do I check in with a QR code? To use QR code check-in:

   1. Generate your personal QR code in Library module → My Account → QR Code
   2. Arrive at the library entrance during operating hours
   3. Scan your QR code at the entrance kiosk
   4. Wait for the green checkmark confirmation
   5. Proceed to collect any reserved materials

   This logs your visit and grants access to study areas and reserved books.
6. What happens if my reserved book is unavailable? If your reserved book becomes unavailable:

   • You'll receive an immediate email notification
   • You can choose another copy of the same book if available
   • Select an alternative book from our recommendations
   • Your reservation priority remains intact for the next available copy
   • Contact library staff if you need assistance finding alternatives

   The system automatically manages waitlists and notifications.
7. How do I view my current loans? To check your current loans:

   • Go to "My Loans" section in the Library module
   • View all currently borrowed books with due dates
   • See renewal options and overdue notices
   • Check current fines or fees (if any)
   • Access your complete borrowing history

   Color-coded indicators show safe (green), approaching due (yellow), and overdue (red) items.
8. How long can I borrow a book? Our loan periods vary by material type:

   Standard Books: 14 days with 7-day automatic grace period
   Reference Materials: 3-7 days (no renewal)
   Reserve Materials: 2-24 hours (varies by instructor)

   You can renew books up to 3 times if no one else has reserved them, extending total loan to 42 days.
9. How do I cancel a reservation? To cancel a library reservation:

   1. Access "My Reservations" in the Library module
   2. Find the reservation you want to cancel
   3. Click the "Cancel" button
   4. Confirm the cancellation

   Cancellations made more than 2 hours in advance receive full refunds. Late cancellations may forfeit reservation fees.
10. Can teachers reserve books too? Yes, teachers have enhanced borrowing privileges:

   ✓ Same reservation access as students
   ✓ Extended loan periods for curriculum materials
   ✓ Priority access to academic resources
   ✓ Ability to reserve multiple books simultaneously
   ✓ Special marking for teacher reservations in the system

   Teachers can also request special acquisitions for classroom use.
11. How do I see due dates? Due dates are prominently displayed in "My Loans" section with color coding:

   🟢 Green: Safe (more than 3 days remaining)
   🟡 Yellow: Approaching due date (1-3 days remaining)
   🔴 Red: Overdue (past due date)

   You also receive email reminders 3 days before due date and daily notifications for overdue books.
12. Where can I find book availability? Book availability is shown in real-time in the catalog search results:

   🟢 Green checkmarks: Available for reservation
   🟡 Yellow clocks: Reserved but available soon
   🔴 Red X's: Currently unavailable/checked out

   You can also filter search results by availability status and see expected return dates.
13. Can I request a book that is not listed? Yes, use the "Special Request" feature:

   1. Access the Library module and click "Special Request"
   2. Provide complete book details (title, author, ISBN if known)
   3. Explain why you need this specific book
   4. Submit your request for staff review

   Library staff will attempt acquisition through inter-library loan or purchase. You'll be notified when available (typically 1-2 weeks).
14. How do I report a missing book? To report a missing book:

   1. Use the "Report Issue" button in the Library module
   2. Select "Missing Book" from the dropdown menu
   3. Provide the book details and exact location where it should be
   4. Add any additional observations
   5. Submit the report

   Library staff will investigate and update the book's status accordingly.
15. Is the catalog updated in real time? Yes, our catalog updates in real-time:

   ✓ Books checked in/out update immediately
   ✓ Reservations and cancellations reflect instantly
   ✓ New acquisitions added within 24 hours
   ✓ Availability status refreshes every 5 minutes
   ✓ Reported issues (missing/damaged) update immediately

   This ensures you always see current, accurate information.
16. How do I print or save my reservation? After confirming a reservation:

   1. Click the "Print/Save" button on the confirmation page
   2. Choose to download as PDF or print directly
   3. The confirmation includes:
      - Reservation number
      - Pickup details and location
      - Due date and return instructions
      - Library contact information

   Keep this for your records and pickup reference.
17. What if my library QR code does not work? If your QR code fails to scan:

   1. Try regenerating it in Library module → My Account → Settings
   2. Ensure your device camera is clean and well-lit
   3. Check that you're using the correct QR code (not expired)
   4. If problems persist, contact library staff for manual check-in
   5. Have your student ID ready as backup

   Staff can manually verify your identity and grant access.
18. How do I extend my borrowing period? To extend your borrowing period:

   1. Go to "My Loans" in the Library module before your due date
   2. Click "Renew" next to the book you want to extend
   3. If no one has reserved the book, it will be extended automatically
   4. Standard extension is the full loan period (usually 14 days)
   5. You can renew up to 3 times per book

   Extensions are not guaranteed if the book is on reserve.
19. How can I access library reports? Library reports access varies by role:

   Students: Can view personal borrowing statistics in "My Account" → "Borrowing History"
   Teachers: Access to class-related borrowing reports through admin dashboard
   Staff: Full access to library analytics and reports through admin panel

   Personal statistics include borrowing frequency, favorite subjects, and due date compliance.
20. How do I check if a book is available? To check book availability:

   1. Search for the book in the Library module catalog
   2. Look for the availability status indicator:
      - "Available" (green): Ready for reservation
      - "Reserved" (yellow): On hold for another student
      - "Checked Out" (red): Currently borrowed
   3. For checked-out books, see the expected return date
   4. If available, proceed with reservation
   5. If unavailable, check for other copies or set up notifications

Library Follow-up Questions:
- What if I forget my reservation date?
- Can I reserve multiple books at once?
- How do I know if my reservation was approved?
- What happens if I return a book late?
- Can I renew a book I'm already borrowing?
- How do I find books on a specific topic?
- What if the book I want is damaged?
- Can I suggest books for the library to purchase?
- How do I access e-books and digital resources?
- What are the library's operating hours?

Clinic FAQ Answers:
1. How do I log daily health data? To log your daily health data:

   1. Access the Clinic module from your dashboard
   2. Click on "Daily Health Log"
   3. Fill in your metrics:
      - Water intake (number of 8oz glasses)
      - Sleep hours and quality rating (1-10)
      - Current weight (optional)
      - Mood level (1-10 scale)
      - Any symptoms or notes
   4. Save your entry to update your health record

   Consistent logging helps clinic staff monitor your wellness trends.
2. What health metrics can I track? The Clinic module tracks comprehensive health metrics:

   ✓ Water intake (glasses consumed daily)
   ✓ Sleep duration and quality (hours + 1-10 rating)
   ✓ Body weight (optional tracking)
   ✓ Mood levels (1-10 emotional scale)
   ✓ General symptoms and health notes
   ✓ Medication adherence reminders
   ✓ Exercise activity logging
   ✓ Custom health observations

   All data is stored privately and visualized in charts and summaries.
3. How do I record water intake? To record your water intake:

   • Open the Clinic module's daily health log
   • Find the "Water Intake" section
   • Enter the number of 8oz glasses you've consumed
   • Update throughout the day as you drink
   • The system shows your progress toward the 8-glass daily goal

   Consistent hydration tracking supports your overall health monitoring.
4. How do I update my sleep log? To update your sleep log:

   1. Navigate to the "Sleep Tracker" in the Clinic module
   2. Enter the number of hours you slept last night
   3. Rate your sleep quality on a scale of 1-10
   4. Add any notes about sleep disturbances or dreams
   5. Save the entry

   This helps clinic staff monitor your sleep patterns and provide recommendations.
5. How do I request a clinic visit? To request a clinic visit:

   1. Open the Clinic module and click "Request Visit"
   2. Describe your symptoms or reason for visit in detail
   3. Select your preferred date and time from available slots
   4. Choose urgency level (routine/urgent/emergency)
   5. Submit the request

   Clinic staff will review and confirm your appointment within 24 hours.
6. Can I review my clinic visit history? Yes, you can review your complete clinic visit history:

   • Access "Visit History" in the Clinic module
   • View all past consultations with detailed records
   • See dates, symptoms discussed, and treatments recommended
   • Access follow-up instructions and clinic notes
   • Each visit is documented with timestamps and staff signatures

   This comprehensive history helps track your health journey over time.
7. Is my health data private? Your health data is strictly protected:

   ✓ HIPAA-compliant privacy standards
   ✓ Only authorized medical staff can access your records
   ✓ Teachers, administrators, and other students cannot view your data
   ✓ All information is encrypted and securely stored
   ✓ You control sharing permissions for your health information

   Clinic records are confidential and used only for your medical care.
8. How do I see my health record summary? To view your health record summary:

   1. Go to "Health Summary" in the Clinic module
   2. View comprehensive charts and statistics including:
      - Average sleep quality over time
      - Water intake trends and patterns
      - Weight changes and BMI tracking
      - Common symptoms and frequency
      - Overall health pattern analysis
   3. Use date filters to view specific time periods
   4. Export summary reports if needed

   This helps you track your wellness progress and identify health trends.
9. What should I do if I feel unwell? If you feel unwell:

   1. Immediately log your symptoms in the Clinic module's daily health log
   2. Describe symptoms in detail:
      - Location and type of discomfort
      - Intensity level (mild/moderate/severe)
      - Duration and when symptoms started
      - Any triggers or alleviating factors
   3. If symptoms are severe, request an urgent clinic visit
   4. For emergencies, contact emergency services directly
   5. Monitor your symptoms and update your log as they change

   Early reporting helps clinic staff provide timely care.
10. How do I report symptoms? To report symptoms accurately:

   1. Use the "Symptom Tracker" in the daily health log
   2. Select from common symptoms or describe custom symptoms
   3. Rate severity on a scale (mild/moderate/severe)
   4. Note when symptoms started and any patterns
   5. Add additional details about pain, location, or triggers
   6. Save the entry to update your health record

   Detailed symptom reporting helps clinic staff provide appropriate care.
11. Can teachers view clinic records? No, clinic records are completely private:

   ✗ Teachers cannot access student clinic records
   ✗ Only authorized medical staff (nurses, doctors) have access
   ✗ This separation ensures student privacy and medical confidentiality
   ✗ Teachers may only access general clinic announcements
   ✗ Individual health data is never shared with faculty

   This privacy protection complies with health information laws.
12. How do I update my medication information? To update medication information:

   1. Go to "Medications" section in the Clinic module
   2. Click "Add Medication" for new prescriptions
   3. Enter complete details for each medication:
      - Name and dosage
      - Frequency and timing
      - Prescribing doctor
      - Start date and expected duration
   4. Log when you take medications
   5. Note any side effects or missed doses
   6. Update when prescriptions change

   This helps clinic staff monitor your medication adherence.
13. How do I access clinic announcements? To access clinic announcements:

   • Check the "Announcements" tab in the Clinic module
   • View important health notices and updates
   • Read vaccination schedules and health campaigns
   • See clinic hour changes or policy updates
   • Access mental health awareness information
   • Find general wellness tips from medical staff

   Announcements are updated regularly with relevant health information.
14. What counts as a clinic visit log? Clinic visit logs include:

   ✓ In-person consultations with nurses or doctors
   ✓ Telemedicine appointments and virtual visits
   ✓ Health screenings and routine check-ups
   ✓ Vaccinations and immunization visits
   ✓ Follow-up appointments for ongoing care
   ✓ Emergency or urgent care visits
   ✓ Specialist referrals and consultations

   Each interaction is documented with date, time, and staff member details.
15. How do I change a scheduled appointment? To change a scheduled appointment:

   1. Contact clinic staff through the module's messaging system
   2. Call the clinic office during business hours
   3. Provide your appointment details (date, time, reason)
   4. Request a new date and time from available slots
   5. Confirm the change with clinic staff

   Changes are subject to availability and should be made at least 24 hours in advance.
16. How do I submit a health update? To submit a health update:

   1. Use the "Health Updates" feature in the Clinic module
   2. Describe any changes in your condition or symptoms
   3. Report new symptoms or changes in existing ones
   4. Update medication changes or side effects
   5. Note recovery progress or new concerns
   6. Submit to keep your health record current

   Regular updates help clinic staff provide better ongoing care.
17. How do I check my clinic request status? To check your clinic request status:

   • View "My Requests" in the Clinic module dashboard
   • See current status for each request:
     - Submitted: Request received
     - Under Review: Being evaluated by staff
     - Approved: Appointment scheduled
     - Completed: Request fulfilled
   • Check submission date and last update
   • Receive email notifications for status changes

   Most requests are processed within 24-48 hours.
18. Can I enter multiple daily health logs? Yes, you can enter multiple daily health logs:

   ✓ Add entries throughout the day as conditions change
   ✓ Each log entry is timestamped separately
   ✓ The system combines all entries into a comprehensive daily summary
   ✓ Track symptom changes or medication effects over time
   ✓ View patterns in your health data across multiple entries

   Multiple entries provide more accurate health monitoring.
19. How do I use the clinic module correctly? To use the Clinic module effectively:

   1. Log health data daily for consistent monitoring
   2. Report symptoms immediately when they occur
   3. Request visits when medical attention is needed
   4. Keep medication information updated
   5. Review your health summaries regularly
   6. Use the messaging system for non-urgent questions
   7. Follow up on appointments and recommendations

   Consistent use ensures comprehensive health care support.
20. How do I find my clinic notes? To access your clinic notes:

   • Go to "Clinic Notes" in the Visit History section
   • View detailed notes from each visit including:
     - Assessment and diagnosis
     - Treatment recommendations
     - Prescriptions and medications
     - Follow-up instructions
     - Educational information provided
   • Notes are organized chronologically
   • Each entry includes staff member and timestamp
   • Use search to find specific visits or topics

   Complete documentation supports your ongoing health care.

Clinic Follow-up Questions:
- What if I miss a day of logging?
- Can I edit previous health entries?
- How do I contact the nurse directly?
- What information should I include in a visit request?
- How long does it take to get a clinic appointment?
- What should I do for allergies or medication reactions?
- How do I request a prescription refill?
- Can I bring a friend or family member to appointments?
- What if I need to cancel an appointment?
- How do I access my vaccination records?

Scholarship FAQ Answers:
1. How do I track my scholarship status? To track your scholarship status:

   • Access the Scholarship module dashboard
   • Click on "Status Tracker" to view progress
   • Monitor checklist completion percentages
   • Check document verification status
   • Review approval stages and pending requirements
   • Address any issues or missing items promptly

   Regular monitoring ensures timely completion of your application.
2. What documents are required for TDP? TDP (Tulong Dunong Program) requires these original documents:

   ✓ Original birth certificate
   ✓ Latest grade report cards (last 2 years)
   ✓ Income tax return or barangay indigency certificate
   ✓ 2 recommendation letters from teachers
   ✓ Proof of good moral character
   ✓ Student ID and enrollment verification

   All documents must be originals, not photocopies.
3. What documents are required for TES? TES (Tulong Edukasyon Scholarship) requires:

   ✓ Exam results (if applicable)
   ✓ Financial statements showing family income
   ✓ Academic records and transcripts
   ✓ Certificate of residency
   ✓ Parent/guardian employment verification
   ✓ Proof of enrollment status

   Submit all documents in person for verification.
4. How do I submit scholarship documents? To submit scholarship documents:

   1. Prepare all required physical documents
   2. Visit the scholarship office during business hours (8 AM - 5 PM, Monday-Friday)
   3. Present your valid student ID
   4. Submit the complete set of documents
   5. Receive a receipt with tracking number
   6. Monitor submission status in the module

   In-person submission ensures document authenticity.
5. Can I upload documents online? No online document uploads are supported:

   ✗ The Scholarship module does not support digital uploads
   ✗ This is a physical document verification system only
   ✗ All applications require in-person submission
   ✗ Original documents must be presented for verification
   ✗ Digital copies are not accepted for security reasons

   Physical submission maintains document integrity.
6. Is this system for physical documents only? Yes, this system is exclusively for physical documents:

   ✓ Designed for tracking physical document submissions
   ✓ Maintains checklist of required documents
   ✓ Tracks verification status and progress
   ✓ Ensures authenticity through in-person verification
   ✓ Complies with security and privacy regulations

   No digital document storage or online uploads.
7. How do I check checklist progress? To check your checklist progress:

   • Go to "My Checklist" in the Scholarship module
   • View color-coded status indicators:
     🟢 Green: Document verified and approved
     🟡 Yellow: Submitted, pending verification
     🔴 Red: Missing or rejected document
     ⚪ Gray: Not yet submitted
   • Monitor completion percentages
   • Address any red or yellow items immediately

   Complete checklists ensure faster processing.
8. How do I know if my documents are complete? Your documents are complete when:

   ✓ All checklist items show green checkmarks
   ✓ No red or yellow status indicators remain
   ✓ Application shows 100% completion
   ✓ Scholarship office confirms receipt
   ✓ All required documents are verified

   Contact the office immediately if items show red or yellow.
9. Who can I contact about scholarships? Contact scholarship staff through:

   • Scholarship module's contact information section
   • Email: scholarship@passcollege.edu.ph
   • Phone: Call during business hours
   • In-person: Visit the scholarship office
   • Coordinator: Ask for the scholarship coordinator

   Staff can assist with applications, requirements, and status updates.
10. How do I update my scholarship profile? To update your scholarship profile:

    1. Navigate to "My Profile" in the Scholarship module
    2. Update personal information and contact details
    3. Modify family background information
    4. Update academic records and grades
    5. Adjust financial status details
    6. Save all changes

    Keep information current for accurate eligibility assessment.
11. How do I view scholarship announcements? To view scholarship announcements:

    • Access "Announcements" section in the Scholarship module
    • Check for new scholarship opportunities
    • Review application deadlines and requirements
    • Read updates from the scholarship committee
    • Look for important notices and changes

    Announcements are updated regularly with current information.
12. What happens after I submit documents? After document submission:

    1. Documents are reviewed by the scholarship committee (3-5 business days)
    2. Checklist status updates to show verification progress
    3. Committee evaluates eligibility and completeness
    4. If approved, you'll receive award notification
    5. Disbursement schedule is provided
    6. Further requirements may be requested if needed

    Processing typically takes 1-2 weeks.
13. How do I fix a missing checklist item? To fix a missing checklist item:

    1. Note the specific rejection reason in your checklist
    2. Gather the correct or missing document immediately
    3. Resubmit to the scholarship office
    4. Allow 24-48 hours for status update
    5. Contact office if issues persist

    Most corrections can be made within 7 days to avoid delays.
14. Can teachers help with scholarship status? Teacher access limitations:

    ✓ Teachers can view general scholarship announcements
    ✓ Teachers can see deadlines and requirements
    ✗ Teachers cannot access individual student applications
    ✗ Teachers cannot view personal document status
    ✗ Teachers cannot modify student scholarship records

    Students should contact the scholarship office directly for personal matters.
15. How do I confirm document receipt? To confirm document receipt:

    • Check checklist status in the Scholarship module (updates within 24 hours)
    • Look for status change from "Not Submitted" to "Submitted"
    • Call the scholarship office with your tracking number
    • Verify receipt of specific documents
    • Request confirmation email if needed

    Confirmation ensures your application is being processed.
16. What if a document is rejected? If a document is rejected:

    1. Review the specific rejection reason (e.g., photocopy instead of original)
    2. Gather the correct document immediately
    3. Resubmit within the specified timeframe (usually 7-14 days)
    4. Monitor checklist for updated status
    5. Contact office if you need clarification

    Prompt correction prevents application delays.
17. How do I find scholarship deadlines? To find scholarship deadlines:

    • Check the "Deadlines" calendar in the Scholarship module
    • Review upcoming application deadlines by scholarship type
    • Note that TDP deadlines are typically in January
    • TES deadlines may occur throughout the academic year
    • Set reminders for important dates

    Missing deadlines can affect scholarship eligibility.
18. Can I change my scholarship type? To change scholarship types:

    1. Contact the scholarship office to discuss changes
    2. Determine if you meet new scholarship requirements
    3. Check availability of the desired scholarship type
    4. Submit any additional required documents
    5. Await approval for the change

    Changes are not guaranteed and depend on various factors.
19. How do I know if I am eligible? To check eligibility:

    ✓ Review criteria in the Scholarship module announcements
    ✓ Ensure good academic standing (minimum 80% average)
    ✓ Demonstrate financial need when required
    ✓ Maintain good conduct record
    ✓ Confirm active enrollment status
    ✓ Meet any additional scholarship-specific requirements

    Eligibility is reassessed periodically.
20. Does the system store my documents? Document storage information:

    ✗ The system does NOT store digital copies of documents
    ✗ Physical documents are stored securely in the scholarship office
    ✗ System only tracks checklist status and verification progress
    ✗ No document files are kept in the digital system
    ✓ Security and privacy regulations are strictly followed

    Physical storage ensures document security and authenticity.

Scholarship Follow-up Questions:
- What if I lose a submitted document?
- Can I get a checklist printout?
- How long does verification take?
- What if I submitted the wrong document?
- Can I apply for multiple scholarships?
- What happens if I miss a deadline?
- How do I appeal a rejected application?
- Can I withdraw from a scholarship?
- What are the scholarship payment schedules?
- How does financial need affect my award?

Guidance FAQ Answers:
0. How do I request a Good Moral Certificate? To request a Good Moral Certificate (Good Moral):

   1. Open the Guidance module from the main dashboard.
   2. Select "Certificates" or "Request Certificate" (label may vary by release).
   3. Choose "Good Moral Certificate" as the document type.
   4. Fill in the form fields: purpose of request, recipient (if any), and any additional notes.
   5. Attach supporting documents if required (e.g., proof of identity, clearance forms).
   6. Submit the request and wait for verification by Guidance staff.
   7. You will receive an email/notification when the certificate is ready for download or pick-up.

   Note: Processing times vary. Contact the Guidance office for urgent requests.

1. How do I request a counseling appointment? To request a counseling appointment:

   1. Open the Guidance module
   2. Click "Request Appointment"
   3. Select counseling type: academic, personal, or career
   4. Choose preferred date and time from available slots
   5. Provide brief description of discussion topics
   6. Submit request and await counselor confirmation

   Appointments are scheduled based on counselor availability.
2. How do I file an incident report? To file an incident report:

   1. Navigate to "Create Incident Report" in the Guidance module
   2. Select incident type (bullying, harassment, academic issue, personal concern)
   3. Provide detailed description including:
      ✓ When and where the incident occurred
      ✓ Persons involved and their roles
      ✓ Any witnesses present
   4. Attach supporting evidence if available
   5. Submit confidentially

   All reports are handled with strict confidentiality.
3. How do I create a guidance case? To create a guidance case:

   1. Choose "Create Case" in the Guidance module
   2. Select case category:
      • Academic advising
      • Career counseling
      • Personal support
      • Crisis intervention
   3. Describe your situation in detail
   4. Indicate urgency level
   5. Submit for counselor assignment

   Cases are assigned to specialized counselors.
4. What information is needed for a report? For incident reports, include:

   ✓ Exact date, time, and location of incident
   ✓ Detailed description of what happened
   ✓ Names and roles of all persons involved
   ✓ Your relationship to the incident
   ✓ Any witnesses and their contact information
   ✓ Immediate actions taken
   ✓ Desired outcomes or resolution

   Be specific and factual for effective investigation.
5. How do I update case details? To update case details:

   1. Access "My Cases" in the Guidance module
   2. Select the active case to update
   3. Click "Edit Details"
   4. Add new information or clarifications
   5. Save changes (automatically timestamped)
   6. Updates are added to case history

   All changes are visible to assigned counselors.
6. How do I check my case status? To check case status:

   • View "Case Status" in the Guidance module dashboard
   • Each case displays:
     ✓ Current status (open/active/under review/resolved)
     ✓ Assigned counselor name
     ✓ Last update date and time
     ✓ Next steps or required actions
   • Status updates are sent via email notifications

   Regular status monitoring ensures timely support.
7. Can teachers submit reports for students? Teacher submission capabilities:

   ✓ Teachers can submit incident reports for students
   ✓ Teachers can create guidance cases on behalf of students
   ✓ Teacher submissions are clearly marked in the system
   ✓ May include additional academic or behavioral context
   ✓ All submissions maintain student confidentiality

   Teachers help ensure comprehensive student support.
8. How do I add reporter information? To add reporter information:

   • System automatically includes your student information
   • For third-party reports, select "Third Party Report"
   • Provide reporter details separately
   • Maintain confidentiality of reported person's identity
   • Specify relationship to the incident

   Third-party reporting protects vulnerable individuals.
9. How do I attach supporting details? To attach supporting details:

   • Use "Attachments" section when submitting reports/cases
   • Upload various file types:
     ✓ Photos and images
     ✓ Documents and statements
     ✓ Screenshots and evidence
   • Files are encrypted and stored securely
   • Maximum file size: 10MB per attachment
   • Multiple files can be uploaded per submission

   Supporting evidence strengthens your case documentation.
10. How do I schedule a follow-up? To schedule a follow-up session:

    1. After initial appointment or case review
    2. Click "Schedule Follow-up" in case details
    3. Select preferred dates from available slots
    4. Provide reasons for the follow-up
    5. Submit request for counselor approval

    Follow-ups help counselors prepare appropriately.
11. Is guidance data confidential? Confidentiality assurance:

    ✓ All guidance records are strictly confidential
    ✓ Protected by privacy laws and regulations
    ✓ Cannot be disclosed without written consent
    ✗ Exceptions: imminent harm or legal requirements
    ✓ Secure storage and access controls
    ✓ Professional ethical standards maintained

    Your privacy and trust are our highest priorities.
12. How do I cancel an appointment? To cancel an appointment:

    1. Contact guidance staff through module messaging
    2. Or call the guidance office directly
    3. Provide appointment details (date, time, counselor)
    4. State reason for cancellation
    5. Confirm cancellation with staff

    Cancellations less than 24 hours in advance may be marked as no-shows.
13. How do I change an appointment request? To change an appointment request:

    ✓ Edit pending requests before counselor confirmation
    ✓ Modify date, time, or description as needed
    ✓ Once confirmed, contact guidance office directly
    ✓ Changes subject to counselor availability
    ✓ Provide new preferred date and time

    Early changes ensure better accommodation.
14. How do I contact a counselor? To contact a counselor:

    • Use "Contact Counselor" feature in the Guidance module
    • Send secure messages through the system
    • Find counselor contact info in "Staff Directory"
    • For urgent matters: call guidance office emergency number
    • Email through official college channels

    Multiple contact methods ensure accessibility.
15. How do I add a reported person? To add a reported person:

    1. Use "Persons Involved" section in incident reports
    2. Enter reported person's details:
       ✓ Full name
       ✓ Relationship to you
       ✓ Role at the college (student/teacher/staff)
       ✓ Contact information (if known)
    3. This helps counselors investigate appropriately
    4. Maintains confidentiality throughout the process

    Complete information enables effective resolution.
16. How do I review past cases? To review past cases:

    • Access "Case History" in the Guidance module
    • View all previous cases including resolved ones
    • Each case shows:
      ✓ Complete timeline of interactions
      ✓ Counselor notes (with your permission)
      ✓ Case outcomes and resolutions
      ✓ Lessons learned and growth insights
    • Track personal development over time

    Case history demonstrates your progress and support received.
17. How do I access guidance announcements? To access guidance announcements:

    • Check "Announcements" tab in the Guidance module
    • Find important updates including:
      ✓ Workshop schedules and dates
      ✓ Career fair information
      ✓ Mental health awareness campaigns
      ✓ General guidance office news
    • Announcements are categorized for easy browsing
    • Regular updates keep you informed

    Stay updated with guidance office activities and resources.
18. How do I report a non-urgent issue? To report a non-urgent issue:

    1. Use "General Inquiry" form in the Guidance module
    2. Describe your concern or issue
    3. Select appropriate category (academic/personal/career)
    4. Indicate preferred response method (email/appointment/phone)
    5. Submit for processing

    Non-urgent issues are typically addressed within 3-5 business days.
19. How do I escalate a concern? To escalate a concern:

    ✓ For immediate assistance: contact guidance office by phone
    ✓ Use emergency contact information in the module
    ✓ For urgent safety/crisis situations:
      1. Call emergency services first (911)
      2. Then notify guidance staff immediately
    ✓ Direct phone contact ensures rapid response

    Safety concerns receive immediate priority attention.
20. What should I do after submission? After submitting a report or case:

    1. Monitor dashboard for status updates
    2. Check email for counselor responses
    3. Keep records of all communications
    4. If no response within expected timeframe (2-3 business days):
       ✓ Follow up through module messaging
       ✓ Contact guidance office directly
    5. Provide additional information if requested

    Active follow-up ensures your concerns are addressed promptly.

Guidance Follow-up Questions:
- What if I need to change case details after submission?
- Can I talk to a counselor anonymously?
- How long does it take to get a response?
- What if I regret submitting a report?
- Can I have multiple active cases?
- What happens during a counseling session?
- Can I bring someone with me to appointments?
- How do I know which counselor to choose?
- What if I need to reschedule multiple times?
- How do I provide feedback about counseling services?

SSC FAQ Answers:
1. How do I join an SSC event? To join an SSC event:

   1. Browse the SSC module's event calendar
   2. Select an event that interests you
   3. Click "Register" button
   4. Provide required information:
      ✓ T-shirt size (if applicable)
      ✓ Dietary restrictions (if applicable)
   5. Confirm your registration
   6. Receive confirmation email with event details and reminders

   Registration ensures your spot and provides important updates.
2. How do I register as a candidate? To register as a candidate:

   1. Open the SSC module
   2. Go to "Candidate Registration"
   3. Select desired position:
      • President
      • Vice President
      • Secretary
      • Treasurer
      • PRO (Public Relations Officer)
      • Other available positions
   4. Submit application with:
      ✓ Personal statement
      ✓ Platform of governance
      ✓ Required documents
   5. Registration typically opens 2 months before elections

   Prepare thoroughly for successful candidacy.
3. How do I view student activities? To view student activities:

   • Access "Activities" section in the SSC module
   • Browse upcoming and past events including:
     ✓ Student council events
     ✓ Workshops and seminars
     ✓ Community service projects
   • Use filters to narrow down:
     ✓ By category (academic, social, service)
     ✓ By date range
     ✓ By location
   • Find activities matching your interests

   Explore diverse opportunities for involvement.
4. How do I check event schedules? To check event schedules:

   • View "Event Calendar" in the SSC module
   • See all SSC-sponsored events with:
     ✓ Dates and times
     ✓ Locations and venues
     ✓ Registration deadlines
   • Subscribe to calendar notifications
   • Receive reminders for registered events
   • Set alerts for important dates

   Stay organized with comprehensive scheduling information.
5. How do I sign up for an event? To sign up for an event:

   1. Find the desired event in the SSC module
   2. Click "Sign Up" or "Register" button
   3. Fill out required information
   4. Submit registration
   5. Note: Some events have limited spots (first-come, first-served)
   6. Receive confirmation email

   Early registration increases chances of participation.
6. How do I get event updates? To get event updates:

   • Enable notifications in SSC module settings
   • Receive updates for:
     ✓ Registered events
     ✓ Schedule changes
     ✓ Cancellations or modifications
   • Check "My Events" section regularly
   • Get personalized notifications
   • Stay informed about event status

   Timely updates help you plan effectively.
7. How do I submit an activity proposal? To submit an activity proposal:

   1. Use "Proposal Submission" form in SSC module
   2. Describe your proposed activity including:
      ✓ Purpose and objectives
      ✓ Target audience
      ✓ Budget requirements
      ✓ Timeline and schedule
      ✓ Expected outcomes
   3. Attach supporting documents
   4. Submit for SSC review and approval

   Well-planned proposals have higher approval chances.
8. How do I view campaign details? To view campaign details:

   • Access "Election Center" in the SSC module
   • View candidate profiles including:
     ✓ Personal background
     ✓ Campaign platforms and goals
     ✓ Debate schedules and information
     ✓ Voting procedures and timelines
   • Each candidate page shows:
     ✓ Contact information for questions
     ✓ Detailed policy positions
     ✓ Campaign promises and priorities

   Informed voting leads to better leadership choices.
9. Can teachers manage SSC events? Teacher management capabilities:

   ✓ Teachers can create and manage SSC events
   ✓ Teachers can approve event proposals
   ✓ Teachers serve as event coordinators and moderators
   ✓ Teachers act as advisors for student council activities
   ✓ Teachers oversee election processes
   ✓ Teachers provide guidance and supervision

   Teachers ensure quality and appropriate event management.
10. How do I join a council meeting? To join a council meeting:

    1. Check "Meetings" section in the SSC module
    2. Browse upcoming council meetings
    3. Select meeting you want to attend
    4. Register if required (some meetings are open to all)
    5. Note: Some meetings are council members only
    6. Confirm attendance and receive details

    Active participation strengthens student voice.
11. How do I check election status? To check election status:

    • Visit "Election Dashboard" in the SSC module
    • View current election information:
      ✓ Voting periods and deadlines
      ✓ Candidate standings (if applicable)
      ✓ Announcement dates for results
    • Monitor real-time updates:
      ✓ Voter turnout statistics
      ✓ Provisional results
      ✓ Election progress indicators

    Stay informed throughout the electoral process.
12. How do I find volunteer opportunities? To find volunteer opportunities:

    • Browse "Volunteer Center" in the SSC module
    • View available positions for:
      ✓ SSC events and activities
      ✓ Community service projects
      ✓ Ongoing council initiatives
    • Filter opportunities by:
      ✓ Time commitment (hours/week)
      ✓ Required skills and experience
      ✓ Cause area or focus
    • Apply for positions matching your interests

    Volunteering builds leadership and community impact.
13. How do I see event participants? To see event participants:

    • For registered events: check "My Events" section
    • View participant lists and contact information (if shared)
    • For public events: see participant counts
    • Note: Individual names may be private
    • Organizer access shows complete participant details
    • Respect privacy settings and guidelines

    Participant information facilitates event coordination.
14. How do I request SSC support? To request SSC support:

    1. Submit "Support Request" through SSC module
    2. Describe what you need:
       ✓ Event planning assistance
       ✓ Funding for activities
       ✓ Promotion and marketing help
       ✓ Logistics and coordination support
    3. Provide detailed requirements and timeline
    4. Submit for council review
    5. SSC can provide resources, volunteers, or guidance

    Clear requests receive better support and approval.
15. How do I update my candidate profile? To update candidate profile:

    1. Access "My Campaign" in the SSC module (if registered candidate)
    2. Update profile elements:
       ✓ Profile photo and images
       ✓ Personal statement and biography
       ✓ Campaign platform details
       ✓ Contact information
       ✓ Campaign promises and goals
    3. Submit changes for election moderator review
    4. Changes are published after approval

    Regular updates maintain campaign relevance and appeal.
16. How do I check activity approval? To check activity approval:

    • View "My Proposals" in the SSC module
    • Check status of submitted proposals:
      🟡 Submitted: Under initial review
      🟡 Under Review: Being evaluated by council
      🟢 Approved: Activity can proceed
      🔴 Rejected: Proposal declined
      🟡 Needs Revision: Changes requested
    • Approved activities appear in public event calendar
    • Monitor status changes and notifications

    Timely follow-up ensures activity implementation.
17. How do I view SSC announcements? To view SSC announcements:

    • Check "Announcements" board in the SSC module
    • Find official communications including:
      ✓ Election updates and schedules
      ✓ Event reminders and changes
      ✓ Policy updates and new procedures
      ✓ Important notices from council leadership
    • Announcements are categorized for easy navigation
    • Regular checking keeps you informed

    Stay updated with council activities and decisions.
18. How do I cancel event registration? To cancel event registration:

    1. Go to "My Events" in the SSC module
    2. Find the event you want to cancel
    3. Click "Cancel Registration" button
    4. Confirm cancellation
    5. Note cancellation policies:
       ✓ More than 48 hours: may allow refunds
       ✓ May transfer spot to waitlist
       ✓ Late cancellations: may forfeit deposits

    Early cancellation helps others participate.
19. How do I give feedback on an event? To give event feedback:

    1. After attending an event
    2. Use "Event Feedback" form in SSC module
    3. Rate the event on various criteria
    4. Provide detailed comments and suggestions
    5. Submit feedback for organizer review
    6. Your input helps improve future events

    Constructive feedback enhances SSC event quality.
20. How do I learn about leadership programs? To learn about leadership programs:

    • Explore "Leadership Development" section in SSC module
    • Find programs including:
      ✓ Leadership workshops and seminars
      ✓ Mentorship programs and opportunities
      ✓ Training sessions for skill development
    • Programs focus on:
      ✓ Communication and public speaking
      ✓ Project management and organization
      ✓ Team building and collaboration
      ✓ Decision making and problem solving
    • Register for programs matching your interests and goals

    Leadership development builds valuable lifelong skills.

SSC Follow-up Questions:
- What if I can't attend a registered event?
- Can I be a candidate for multiple positions?
- How do I withdraw my candidacy?
- What are the requirements for candidates?
- Can I propose my own event?
- How do I become an SSC officer?
- What if I miss an election deadline?
- Can I volunteer for SSC without being a member?
- How do I organize a student petition?
- What are the benefits of joining SSC?

SSAA FAQ Answers:
1. How do I update alumni employment status? To update employment status:

   1. Access the SSAA module
   2. Navigate to "Employment Update" section
   3. Enter current information:
      ✓ Job title and position
      ✓ Company name and industry
      ✓ Salary range (optional)
      ✓ Employment start date
   4. Add job responsibilities and achievements
   5. Submit updates to maintain accurate records

   Regular updates help track alumni career progression.
2. How do I see my progression details? To view progression details:

   • Go to "Academic Progression" in SSAA module
   • View complete educational journey including:
     ✓ Enrollment dates and periods
     ✓ Courses completed with grades
     ✓ Awards and honors received
     ✓ Current graduation status
   • Track academic milestones and achievements
   • Monitor progress over time

   Comprehensive view of your academic journey.
3. How do I check graduation tracking? To check graduation tracking:

   • Visit "Graduation Tracker" in SSAA module
   • View requirements progress with:
     ✓ Courses needed vs. completed
     ✓ Credits earned vs. required
     ✓ Current GPA status
     ✓ Expected graduation date
   • Color-coded indicators show:
     🟢 Completed requirements
     🟡 In-progress items
     🔴 Remaining requirements

   Clear visualization of graduation progress.
4. How do I update career information? To update career information:

   • Use "Career Profile" section in SSAA
   • Update current position details:
     ✓ Job title and industry
     ✓ Skills and certifications
     ✓ Career goals and objectives
   • Add previous work experience
   • Include notable achievements
   • Document professional development activities

   Current information supports career services and networking.
5. Can I access SSAA after graduation? Alumni access information:

   ✓ Alumni retain full SSAA module access
   ✓ Lifelong career support and resources
   ✓ Continued networking opportunities
   ✓ Ongoing PASS College engagement
   ✓ Account remains active indefinitely
   ✗ Some student-specific features may be limited

   Continued support throughout your career journey.
6. How do I add employer details? To add employer details:

   1. Access your SSAA profile
   2. Click "Add Employer" button
   3. Enter company information:
      ✓ Company name and industry
      ✓ Location and company size
      ✓ Your role and responsibilities
   4. Include additional details:
      ✓ Company website
      ✓ Contact information
      ✓ Company description
   5. Save to help other alumni understand your background

   Detailed employer information enhances networking value.
7. How do I view alumni resources? To view alumni resources:

   • Browse "Alumni Resources" section in SSAA
   • Access comprehensive career support including:
     ✓ Career guides and advice
     ✓ Resume templates and examples
     ✓ Interview tips and preparation
     ✓ Professional development webinars
     ✓ Job search resources and tools
     ✓ Networking event information
   • Resources updated regularly with current content

   Extensive library of career development materials.
8. How do I submit job updates? To submit job updates:

   • Use "Job Update" feature in SSAA
   • Report important career changes:
     ✓ New employment opportunities
     ✓ Promotions and advancements
     ✓ Career transitions
     ✓ Job search activities
   • Include detailed information:
     ✓ Position and company details
     ✓ Start date and responsibilities
   • Updates maintain network accuracy and provide career insights

   Timely updates support alumni career tracking.
9. How do I check progression milestones? To check progression milestones:

   • Access "Milestone Tracker" in SSAA
   • View academic and career markers including:
     ✓ Course completions and grades
     ✓ Leadership roles and positions
     ✓ Awards and recognitions
     ✓ Internship experiences
     ✓ Graduation achievements
   • Each milestone shows:
     ✓ Completion date
     ✓ Significance and impact
   • Track development over time

   Comprehensive milestone tracking for personal growth.
10. How do I connect with alumni services? To connect with alumni services:

    • Use "Contact Services" feature in SSAA
    • Reach alumni relations staff for:
      ✓ Career advice and guidance
      ✓ Networking assistance
      ✓ Event planning support
      ✓ General alumni support
    • Contact methods include:
      ✓ Secure messaging system
      ✓ Appointment scheduling
      ✓ Alumni hotline access

    Direct access to comprehensive alumni support services.
11. How do I report employment changes? To report employment changes:

    1. Use "Career Changes" form in SSAA module
    2. Provide comprehensive update including:
       ✓ New position details
       ✓ Company information
       ✓ Salary range (optional)
       ✓ Reason for change
    3. Submit for record keeping
    4. Information helps track career trajectories
    5. Supports career services and alumni statistics

    Detailed reporting enables better alumni services.
12. How do I find alumni announcements? To find alumni announcements:

    • Check "Announcements" board in SSAA
    • Find important updates including:
      ✓ Alumni news and updates
      ✓ Reunion information and schedules
      ✓ Career opportunities and job postings
      ✓ Professional development events
      ✓ Success stories and achievements
      ✓ Association updates and announcements

    Stay connected with alumni community activities.
13. Can teachers access SSAA data? Teacher access limitations:

    ✓ Teachers can access general alumni statistics
    ✓ Teachers can view success stories and achievements
    ✗ Teachers cannot view individual alumni records
    ✓ Alumni data is strictly protected for privacy
    ✓ Teachers may access aggregated information
    ✓ Limited access for institutional research purposes

    Privacy protection ensures alumni data security.
14. How do I view alumni profiles? To view alumni profiles:

    • Use "Alumni Directory" in SSAA
    • Search and browse alumni profiles (privacy permitting)
    • Search filters available:
      ✓ Graduation year
      ✓ Industry or profession
      ✓ Location and region
      ✓ Name or specific criteria
    • Profiles show:
      ✓ Professional information
      ✓ Achievements and experience
      ✓ Contact options (if enabled)

    Powerful networking tool for career connections.
15. How do I verify graduation records? To verify graduation records:

    1. Access "Record Verification" in SSAA
    2. Request official documents:
       ✓ Transcripts and academic records
       ✓ Diploma copies
       ✓ Enrollment verification letters
    3. Specify purpose and delivery method
    4. Submit request for processing
    5. Requests processed within 3-5 business days

    Official verification for employment and other purposes.
16. How do I update personal status? To update personal status:

    • Go to "Personal Profile" in SSAA
    • Update contact information:
      ✓ Current address and location
      ✓ Phone number and email
      ✓ Emergency contact details
    • Add personal milestones and achievements
    • Keep information current for communications
    • Ensure accurate alumni database records

    Current information enables effective alumni engagement.
17. How do I request alumni support? To request alumni support:

    1. Submit "Support Request" through SSAA
    2. Describe your needs clearly:
       ✓ Career advice and counseling
       ✓ Networking assistance
       ✓ Mentorship opportunities
       ✓ Financial guidance
    3. Explain your current situation
    4. Specify preferred assistance type
    5. Requests reviewed within 2-3 business days

    Comprehensive support system for alumni needs.
18. How do I share success stories? To share success stories:

    1. Use "Success Stories" submission form in SSAA
    2. Share your professional journey:
       ✓ Career achievements and milestones
       ✓ Challenges overcome
       ✓ Valuable advice for current students
    3. Submit for review and approval
    4. Stories featured in:
       ✓ Alumni publications
       ✓ Student motivation materials
       ✓ Career guidance resources

    Your success inspires future generations.
19. How do I find alumni events? To find alumni events:

    • Browse "Events Calendar" in SSAA
    • Discover various event types:
      ✓ Alumni reunions and gatherings
      ✓ Professional networking events
      ✓ Career fairs and job expos
      ✓ Workshops and seminars
      ✓ Social gatherings and mixers
    • Filter events by:
      ✓ Location and venue
      ✓ Event type and category
      ✓ Date and time preferences

    Extensive calendar of alumni engagement opportunities.
20. How do I track long-term support? To track long-term support:

    • Access "Support History" in SSAA
    • View all alumni services interactions including:
      ✓ Career counseling sessions
      ✓ Networking assistance provided
      ✓ Event attendance records
      ✓ Support requests and responses
    • Monitor engagement over time
    • Track the value of alumni services received

    Complete history of your alumni support journey.

SSAA Follow-up Questions:
- What if I change jobs frequently?
- Can I update my contact information?
- How do I connect with other alumni?
- What career services are available?
- Can I access SSAA as a current student?
- How do I find alumni mentors?
- What if I need to update my education records?
- Can I volunteer for alumni events?
- How do I donate to the alumni association?
- What benefits do alumni receive?
﻿
passlogo.png:1 
 Failed to load resource: the server responded with a status of 404 (Not Found)
scholarship_dashboard.php:652 Error loading applications: TypeError: Cannot set properties of null (setting 'innerHTML')
    at displayMyApplications (scholarship_dashboard.php:705:37)
    at loadMyApplications (scholarship_dashboard.php:649:17)
loadMyApplications	@	scholarship_dashboard.php:652
ADVANCED NLP QUESTIONS AND ANSWERS:

Contextual & Complex Questions:

1. I'm having trouble with my health insurance or medical questions - which module should I use?
Answer: The Clinic module is for tracking your daily health metrics (water intake, sleep, mood) and requesting clinic visits at PASS College. However, if you have questions about insurance, medications outside campus, or serious medical conditions, please contact:
- Campus Health Center directly for medical advice
- Your personal healthcare provider for serious concerns
- Administration office for insurance-related questions
The Clinic module focuses on preventive wellness tracking and campus health services only.

2. I lost my library QR code and forgot my student ID - how can I still check in?
Answer: Don't worry! Here are your alternatives:
✓ Generate a new QR code immediately in Library → My Account → Settings (reinstalls in seconds)
✓ Use your full name and registration number at the check-in desk
✓ Staff can manually verify your identity with any official ID
✓ You can use your email address as backup identification
If all else fails, contact library staff and they'll assist with manual verification. No appointment needed.

3. I submitted scholarship documents but can't find them in the system - did they get lost?
Answer: Physical documents in the Scholarship module go through this process:
1. Documents must be submitted IN PERSON at the scholarship office (not through the system)
2. After submission, staff enters them into the checklist (may take 1-2 business days)
3. Once entered, you'll see updates in "Checklist Status" in your Scholarship dashboard
If you submitted documents but don't see them after 2 days, contact the scholarship office with your receipt number to track the submission.

4. Can I change my scholarship type from TDP to TES after I start?
Answer: Changing scholarship types is possible but requires specific steps:
✓ Contact the scholarship office BEFORE submitting all documents
✓ Request a "Scholarship Type Change" form
✓ You may need to resubmit different document requirements
✓ Timing is critical - changing after partial submission may delay processing
Recommendation: Meet with scholarship staff first to discuss requirements for each type before starting.

5. My counseling appointment was cancelled - will I lose my case progress?
Answer: No, your case progress is permanently saved. Here's what happens:
✓ All case details remain in "My Cases" in the Guidance module
✓ Your historical notes, reports, and follow-ups stay accessible
✓ You can request a new appointment anytime to continue the case
✓ The counselor can review your complete history before the next session
Your case is treated as continuous, even with appointment gaps. Just reschedule when ready.

Troubleshooting & Error Handling:

6. The system keeps saying "Book not available" but I just saw it in the catalog!
Answer: This usually means one of three things:
1. Real-time sync delay (the catalog is updating - refresh in 5 minutes)
2. Someone just reserved it seconds before you (check "Waitlist" option)
3. Staff just checked it in but haven't shelved it yet (ask library staff to hold it)

Quick fixes:
• Clear your browser cache (Ctrl+Shift+Delete)
• Try a different search term to find the same book
• Join the waitlist to get notified when it's available again
• Ask library staff to help you find an alternative copy

7. My clinic health log disappeared - did I lose all my data?
Answer: Your data is not lost! Common reasons this happens:
✓ Accidentally used the wrong date filter (check date range in "Health Records")
✓ Your session expired (log out and back in)
✓ The page didn't refresh properly (refresh your browser)
✓ Data takes 30 seconds to sync after entry (wait before leaving the page)

Recovery steps:
1. Go to Clinic → Health Records and check all date filters
2. Search for specific dates you logged data
3. Contact clinic staff with your dates - they have server backups
Your data is secure and recoverable - the system keeps 7-year archives.

8. I'm getting "Permission Denied" when trying to access a module - am I locked out?
Answer: This doesn't mean you're locked out. Check these quickly:
✓ Are you logged in with the correct account? (sign out and sign in again)
✓ Does your role have access to this module? (some modules are role-specific)
✓ Is your account activated? (check your email for activation links)
✓ Is there a maintenance window? (check system announcements)

If still locked out after these checks:
- Screenshot the error message
- Contact IT support with your student ID
- They can reset your permissions within 1-2 hours
Include the exact error code from the message.

Module Integration & Cross-Functional Questions:

9. Can I use my library account to access scholarship documents or vice versa?
Answer: No, modules are intentionally separate for security and privacy:
🔐 Library Module: Book management only (no access to other data)
🔐 Scholarship Module: Document tracking only (no access to health or counseling)
🔐 Clinic Module: Health records (completely private, separate from other modules)
🔐 Guidance Module: Counseling cases (isolated from academic and health records)
🔐 SSC Module: Student activities (separate from administrative modules)

This separation protects your privacy. Each module only shows relevant data for that service.

10. If I'm having a health crisis, should I use the Clinic module or go to the hospital?
Answer: For immediate emergencies, ALWAYS go to the hospital first:
🚑 Emergency: Call 911 or go to the nearest emergency room NOW
💊 Urgent but not life-threatening: Contact campus clinic and explain urgency
📱 Non-emergency health concerns: Use Clinic module to request a visit

The Clinic module is for:
✓ Scheduling routine visits
✓ Logging wellness data
✓ Tracking health trends
✗ NOT for emergencies

After emergency treatment, you can update the Clinic module with the visit summary for your health records.

Question Variations & Synonyms (NLP Recognition):

These questions are equivalent and will trigger the same answers:
- "Where's my book?" = "How do I check the status of my reservation?"
- "I need a doctor" = "How do I request a clinic visit?"
- "Add a counselor" = "How do I submit an incident report?"
- "Sign me up for an event" = "How do I join an SSC event?"
- "Check my finances" = (Not available - redirects to Finance office)
- "Update my job info" = "How do I update my employment status in SSAA?"
- "Can't find my password" = (Redirects to password reset system)
- "Something's broken" = (Escalates to IT support)

Common User Mistakes & Prevention:

11. I submitted my scholarship documents through the system but they won't upload - why?
Answer: The Scholarship module does NOT accept uploads! This is the most common mistake.
❌ WRONG: Trying to upload documents through the system
✅ CORRECT: Printing and submitting physical documents IN PERSON at the office

What to do:
1. Go to Scholarship → View Checklist
2. Print the required document list
3. Gather the physical documents
4. Visit the scholarship office during business hours (M-F 9am-5pm)
5. Hand over documents and receive a receipt
6. Staff will log them in the system (1-2 business days)
7. Check your checklist status online

Important: The system is for tracking only, not for submission.

12. I'm a teacher but I can only see some students' records - is something wrong?
Answer: This is normal! Teacher access is intentionally restricted:
👨‍🎓 You can see: Students enrolled in your courses
❌ You cannot see: Other teachers' students or sensitive data
✓ Library loans: Only if you requested a report for academic purposes
✓ Guidance cases: Only if you referred the student
✗ Health records: Completely hidden (even from teachers)
✗ Scholarship status: Private to each student

This protects student privacy. Contact administration if you need specific reporting access.

Sentiment-Aware Responses:

13. I'm so frustrated - I've been trying to use the library system for an hour and nothing works!
Answer: I understand your frustration - technical issues are really annoying! Let me help quickly. Here's what usually works:

First aid (30 seconds):
1. Close your browser completely (not just the tab)
2. Clear your cache: Ctrl+Shift+Delete
3. Restart your computer (if you have time)
4. Log back in

If that doesn't work (2 minutes):
- Try a different browser (Chrome, Firefox, Safari, Edge)
- Use your phone or a different computer
- Try the system at different time (peak hours can be slow)

Still stuck? I'm here to help:
- Tell me exactly what error message you see
- Let me know which step fails
- I can help troubleshoot or escalate to IT

You've got this - most issues are simple fixes!

14. I'm worried my health data might be seen by people I don't want seeing it
Answer: This is an important concern and I appreciate you asking. Here's the real security:
🔒 HIPAA Compliance: All health data is legally protected
🔒 Encryption: Your data is encrypted in storage and transmission
🔒 Limited access: Only clinic medical staff can access your records
✓ Parents, friends, other students: Cannot access your health data
✓ Teachers, administrators: Cannot access your health data
✓ You control: Who gets to see your information through sharing settings

Confidence level: Your health privacy is one of our highest priorities. If you ever feel your data is being accessed improperly, contact IT support immediately - we investigate all privacy concerns.

Escalation & When to Ask for Help:

15. I've tried everything and I still can't solve this - what do I do?
Answer: Great question - knowing when to escalate is important! Here's the process:

Step 1: Document everything
- Take a screenshot of the error
- Note the date and time
- Write down exact steps you took
- Save the error message

Step 2: Contact the right person
- Library issues → Library staff or library IT extension
- Clinic issues → Clinic office (Room 234)
- Scholarship issues → Scholarship office (Finance Building 2nd floor)
- Guidance issues → Counseling office
- SSC issues → Student council advisor
- General system issues → IT Help Desk (IT@passcollegepsu.edu)

Step 3: Give them your documentation
- Be specific about what happened
- Include your student ID
- Describe when the issue started

Response time:
- Urgent/system down: 1-2 hours
- High priority: Same business day
- Standard: Within 2-3 business days

You don't need to solve this alone - that's what support staff are for!

Follow-up Recommendations & Next Steps:

16. I successfully completed the library orientation - what else should I know?
Answer: Great job on the orientation! Here's what to do next:

Immediate (Today):
✓ Make your first reservation to practice
✓ Try the QR code check-in system
✓ Review your lending limits and due dates
✓ Bookmark the catalog for quick access

This week:
✓ Set up notification preferences (email reminders)
✓ Explore advanced search filters
✓ Check if your classes have assigned readings available
✓ Look at the "New Arrivals" section

Going forward:
✓ Use the system regularly to stay familiar
✓ Take advantage of extension options if needed
✓ Report issues promptly so staff can help
✓ Attend refresher trainings if available

Pro tip: Bookmark "My Loans" - you'll visit it often!

17. I've organized my scholarship documents but I'm nervous about submitting them - any tips?
Answer: Nervousness is normal! Having organized documents is already a huge win. Final tips before submission:

Preparation checklist:
☑ Print the official checklist from the system
☑ Verify each document on the list is collected
☑ Make extra copies (keep one for yourself)
☑ Number your pages so staff knows they're all there
☑ Bring them in a folder (organized submission looks professional)
☑ Take photos of each page as backup

At submission:
✓ Arrive during office hours (M-F 9am-5pm)
✓ Bring your student ID
✓ Ask the staff to verify they have everything
✓ GET A RECEIPT with a submission number
✓ Take a photo of your receipt

After submission:
✓ Check the system 2 days later for updates
✓ If nothing appears, use your receipt number to follow up
✓ Keep your receipt until verification is complete

You've got a solid plan - this will go smoothly!

Data Analysis & Insights Questions:

18. How can I analyze my health trends from the Clinic module?
Answer: Great question! Here's how to extract insights from your health data:

Accessing your data:
1. Go to Clinic → Health Summary → Analytics
2. View auto-generated charts showing:
   - Average sleep quality over 30/90/180 days
   - Water intake consistency patterns
   - Weight trends with calculated BMI
   - Mood correlations with sleep/exercise
   - Visit frequency and outcomes

What the charts show you:
📊 Trends: Are you sleeping better/worse over time?
📊 Patterns: Do you sleep better on certain days?
📊 Correlations: Does more water intake improve mood?
📊 Anomalies: Unusual spikes or drops in metrics

How to use insights:
1. Identify patterns (e.g., poor sleep on weekends)
2. Plan interventions (e.g., better weekend routine)
3. Share data with clinic staff for recommendations
4. Track improvement after changes
5. Export reports for personal records

Export options:
✓ PDF report (personal archive)
✓ CSV data (for Excel analysis)
✓ Printable charts (to share with doctors)

19. I want to understand my scholarship application progress - what do the checklist statuses mean?
Answer: Scholarship checklist statuses explained:

Status Meanings:
🔵 NOT_STARTED (Blue) → Document not yet submitted
🟡 IN_PROGRESS (Yellow) → Submitted but being verified
🟢 VERIFIED (Green) → Staff confirmed, acceptable
🔴 REJECTED (Red) → Document doesn't meet requirements
⚠️ NEEDS_REVISION (Orange) → Close, but needs corrections

Progress calculation:
- Completed items: (Verified count / Total required) × 100
- Example: 18 verified out of 25 items = 72% complete
- You see progress bar and percentage in dashboard

Timeline interpretation:
- Weeks 1-2: Most items still NOT_STARTED (normal)
- Weeks 2-4: Items move to IN_PROGRESS as submitted
- Weeks 4-6: Items become VERIFIED as reviewed
- Week 6+: Expect mostly VERIFIED status

If you see REJECTED:
- Read the rejection reason (attached to that item)
- Get the correct document
- Resubmit with corrected version
- Staff will re-review (usually 2-3 days)

Common rejection reasons:
✗ Missing pages or signatures
✗ Unreadable/blurry photos
✗ Expired documents (ID, etc.)
✗ Wrong document version
✗ Incomplete information

What to do: Contact scholarship office with the rejection reason - they'll guide you to fix it.

20. I'm comparing my health data with friends - should I be concerned if my metrics differ?
Answer: Great question about data comparison. Important context first:

Individual variation is NORMAL:
✓ Sleep needs vary: 7-9 hours is healthy range
✓ Water intake varies: 6-12 glasses depending on activity
✓ Mood varies: Daily changes are expected
✓ Weight varies: 2-3 pounds daily is normal fluctuation
✓ Exercise needs vary: Individual fitness levels differ

What NOT to do:
❌ Worry about minor differences from friends
❌ Compare your metrics to "perfect" averages
❌ Change habits based only on someone else's data
❌ Ignore concerning trends just because others are similar

What TO do:
✓ Focus on YOUR personal trends (getting better/worse?)
✓ Notice YOUR patterns (sleep affects YOUR mood?)
✓ Ask clinic staff about YOUR specific concerns
✓ Track YOUR progress toward YOUR goals
✓ Use data to improve YOUR health (not for comparison)

Red flags (worth discussing with clinic):
🚩 Sudden dramatic changes (not gradual trends)
🚩 Consistently poor sleep affecting your function
🚩 Persistent low mood despite good sleep/exercise
🚩 Significant weight changes unexpectedly
🚩 Frequent illness or symptoms

Remember: Health is individual. Your unique data is only meaningful when compared to yourself over time, not to others.

ANSWERING GUIDELINES:
- When answering, first identify the most relevant module (Library, Clinic, Scholarship, Guidance, SSC, SSAA).
- Use the module descriptions, step-by-step guides, and FAQ list as your knowledge base. Treat them as authoritative facts.
- If the user's question is NOT an exact FAQ match, attempt semantic matching: map synonyms and intent to the nearest module and relevant steps.
- If the intent is still ambiguous, ask one concise clarifying question before giving a definitive answer.
- Response format:
   1) Short summary (1-3 sentences) stating the direct answer or recommendation.
   2) Actionable next steps (2-5 bullet points) the user can take immediately, including direct links to dashboard pages when relevant.
   3) If required, include a brief confidence note (e.g., "I believe this applies to the Library module") and suggest escalation (contact office or IT) if data access is needed.
- Do NOT invent system behavior or personal data. If the answer requires personal account data, prompt the user to log in or provide minimal identifying context.
- When the user asks for policies, deadlines, or legal/medical advice beyond the system's scope, give a safe short answer and direct them to the appropriate office.
- For troubleshooting, provide diagnosis steps first (quick checks), then escalation steps with required information (screenshots, student ID, timestamps).

EXAMPLE HANDLING RULES (for the model):
- User: "Where's my book?" → Module: Library → Short: "Check My Loans or search the catalog." → Steps: open Library → My Loans → check availability link.
- User: "My clinic log disappeared" → Module: Clinic → Short: "Your data is likely safe; try these checks." → Steps: refresh, check filters, re-login, contact clinic with dates.
- User: "I need help but not sure which module" → Ask: "Can you describe the problem in one sentence (e.g., library, health, scholarship)?" Then map to module and answer.


EOT
);

require_once __DIR__ . '/../../includes/ai_management.php';
ensure_ai_management_schema(SYSTEM_PROMPT, SERVICE_CONTEXT);

?>