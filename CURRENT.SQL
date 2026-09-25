-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2026 at 01:57 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `support_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_knowledge`
--

CREATE TABLE `ai_knowledge` (
  `id` int(10) UNSIGNED NOT NULL,
  `entry_type` enum('knowledge','faq') NOT NULL DEFAULT 'knowledge',
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `question` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_knowledge`
--

INSERT INTO `ai_knowledge` (`id`, `entry_type`, `title`, `category`, `question`, `content`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 'knowledge', 'PASS College Support Services', 'General', NULL, 'The PASS College Support Services system provides separate support modules for Library, Clinic, Scholarship, Guidance, SSC, and SSAA. The AI assistant must answer only from active information saved in the administrator-managed Knowledge Base and Teach Your Agent entries. If the saved entries do not contain the answer, the assistant must clearly say that the information is not yet available.', 1, '2026-09-16 04:19:57', '2026-09-16 04:19:57'),
(5, 'knowledge', 'Library Services', 'library', NULL, 'The Library module manages book inventory, catalog searching, book reservations, lending status, reservation history, digital resources, and QR-code check-ins. Students and teachers may view available library materials and reservation information through the Library module. To reserve a book, open the Library module, search the catalog, choose an available copy, and submit the reservation.', 1, '2026-09-16 04:20:29', '2026-09-16 04:20:29'),
(6, 'knowledge', 'Clinic Services', 'Clinic', NULL, 'The Clinic module records daily health information, including water intake, sleep, symptoms, wellness entries, clinic visits, and visit summaries. Clinic records are private and separate from Guidance counseling records. To request clinic assistance, open the Clinic module and submit the appropriate clinic visit request.', 1, '2026-09-16 04:20:53', '2026-09-16 04:20:53'),
(7, 'knowledge', 'Scholarship Services', 'Scholarship', NULL, 'The Scholarship module supports TDP and TES scholarship application tracking through a checklist workflow. Scholarship requirements are submitted as physical documents to the scholarship office. The system tracks checklist completion and document verification status. Students should review the checklist and prepare the required documents before submission.', 1, '2026-09-16 04:21:19', '2026-09-16 04:21:19'),
(8, 'knowledge', 'Guidance Services', 'Guidance', NULL, 'The Guidance module supports counseling appointment requests, academic or career guidance, incident reports, and guidance cases. Students can submit their concern, provide the required details, and monitor the case status. Guidance records are separate from Clinic health records and Scholarship documents.', 1, '2026-09-16 04:21:44', '2026-09-16 04:21:44'),
(9, 'knowledge', 'SSC Services', 'SSC', NULL, 'The SSC module manages student council events, announcements, candidate registration, student activities, event schedules, and participation tracking. Students may browse available activities, register when registration is open, and monitor event announcements through the SSC module.', 1, '2026-09-16 04:22:31', '2026-09-16 04:22:31'),
(10, 'knowledge', 'SSAA Services', 'SSAA', NULL, 'The SSAA module supports student progression, alumni status, graduate information, employment updates, and alumni-related support. Students and graduates may review their profile information and submit updated employment or alumni details through the available SSAA features.', 1, '2026-09-16 04:22:53', '2026-09-16 04:22:53'),
(11, 'knowledge', 'AI Answering Rules', 'General', NULL, 'The AI assistant must not invent procedures, requirements, schedules, fees, contact details, or policy information. It must use only active entries in the Knowledge Base and Teach Your Agent tabs. When information is missing or unclear, the assistant should say that the detail is not yet available and advise the user to contact the responsible office.', 1, '2026-09-16 04:23:12', '2026-09-16 04:23:12'),
(12, 'faq', 'Trained Question', 'General', 'What can you help me with?', 'I can help with verified information about the Library, Clinic, Scholarship, Guidance, SSC, and SSAA services. Please tell me which service you need.', 1, '2026-09-16 04:24:41', '2026-09-16 04:24:41'),
(13, 'faq', 'Trained Question', 'General', 'What can you help me with?', 'I can help with verified information about the Library, Clinic, Scholarship, Guidance, SSC, and SSAA services. Please tell me which service you need.', 1, '2026-09-16 04:26:32', '2026-09-16 04:26:32'),
(14, 'faq', 'Trained Question', 'Library', 'How do I reserve a library book?', 'Open the library catalog, search for the book, select an available copy when reservations are offered, and submit the reservation.', 1, '2026-09-16 04:27:09', '2026-09-23 07:40:56'),
(15, 'faq', 'Trained Question', 'Library', 'How do I borrow a book?', 'Open the Library module, search for the book, check that a copy is available, and follow the borrowing or reservation instructions shown in the system. If you need help with the borrowing process, contact the library staff.', 1, '2026-09-16 04:27:32', '2026-09-16 04:27:32'),
(16, 'faq', 'Trained Question', 'Library', 'How can I check if a book is available?', 'Open the Library module and search the catalog by title, author, or subject. The catalog shows the available book information saved in the system.', 1, '2026-09-16 04:28:15', '2026-09-16 04:28:15'),
(17, 'faq', 'Trained Question', 'Library', 'How do I use the library QR check-in?', 'Open the Library module and use the QR-code check-in when you visit the library. If the QR code does not work, contact the library staff for assistance.', 1, '2026-09-16 04:28:36', '2026-09-16 04:28:36'),
(18, 'faq', 'Trained Question', 'Clinic', 'What health information can I record?', 'The Clinic module supports water intake, sleep, symptoms, daily health metrics, wellness entries, and clinic visit records.', 1, '2026-09-16 04:29:02', '2026-09-16 04:29:02'),
(19, 'faq', 'Trained Question', 'Clinic', 'How do I request a clinic visit?', 'Open the Clinic module and submit the clinic visit request with the required information. You can review your visit summary after the consultation is recorded.', 1, '2026-09-16 04:29:20', '2026-09-16 04:29:20'),
(20, 'faq', 'Trained Question', 'Scholarship', 'Can I upload my scholarship documents online?', 'Scholarship requirements use a physical document submission workflow. Review the checklist, prepare the required documents, and submit them to the scholarship office.', 1, '2026-09-16 04:29:43', '2026-09-16 04:29:43'),
(21, 'faq', 'Trained Question', 'Scholarship', 'How do I check my scholarship application status?', 'Open the Scholarship module and review your checklist completion and document verification status for the TDP or TES application.', 1, '2026-09-16 04:30:09', '2026-09-16 04:30:09'),
(22, 'faq', 'Trained Question', 'Guidance', 'How do I request a counseling appointment?', 'Open the Guidance module, choose the counseling appointment option, provide the required details about your concern, and submit the request. Monitor the case or appointment status for updates.', 1, '2026-09-16 04:30:38', '2026-09-16 04:30:38'),
(23, 'faq', 'Trained Question', 'Guidance', 'Where can I submit an incident report?', 'Open the Guidance module and choose Incident Report. Provide the details of the concern and the involved persons, then submit the report for review.', 1, '2026-09-16 04:31:44', '2026-09-16 04:31:44'),
(24, 'faq', 'Trained Question', 'SSC', 'Where can I see student council events?', 'Open the SSC module to browse student council events, activities, schedules, announcements, and participation information.', 1, '2026-09-16 04:32:15', '2026-09-16 04:32:15'),
(25, 'faq', 'Trained Question', 'SSAA', 'What is the SSAA/alumni module for?', 'SSAA supports student progression, alumni status, graduate information, employment updates, and alumni-related services.', 1, '2026-09-16 04:33:09', '2026-09-16 04:33:09'),
(26, 'faq', 'Trained Question', 'SSAA', 'How do I update my employment information?', 'Open the SSAA module and update your employment or graduate information using the available profile or employment update feature.', 1, '2026-09-16 04:33:46', '2026-09-16 04:33:46'),
(27, 'faq', 'Trained Question', 'General', 'What should I do if the AI does not know the answer?', 'The AI only uses verified information saved in its active knowledge entries. If the answer is unavailable, please contact the responsible PASS College office or ask an administrator to add the verified information to the AI Knowledge Base.', 1, '2026-09-16 04:34:08', '2026-09-16 04:34:08'),
(31, 'faq', 'Trained Question', 'General', 'What is PASS Support Service System?', 'PASS is the school support portal for student, teacher, Guidance, Library, Clinic, SSC, Scholarship, SSAA, and administrative services. Sign in, choose your role, and select the service you need from the dashboard.', 1, '2026-09-23 07:31:19', '2026-09-23 07:40:48'),
(32, 'faq', 'Trained Question', 'General', 'How do I open the student portal?', 'On the PASS login screen, choose Student Login, enter your registered email and password, and select Login. After signing in, use Student Home to open a service.', 1, '2026-09-23 07:31:19', '2026-09-23 07:40:48'),
(33, 'faq', 'Trained Question', 'General', 'How do I open the teacher portal?', 'On the PASS login screen, choose Teacher Login, enter your teacher email and password, and select Login. Your dashboard will show the services allowed for your account.', 1, '2026-09-23 07:31:19', '2026-09-23 07:40:48'),
(34, 'faq', 'Trained Question', 'General', 'How do I find a service?', 'Sign in and open the dashboard sidebar or service menu. Choose the service name, then select the specific task you want to complete.', 1, '2026-09-23 07:31:19', '2026-09-23 07:40:49'),
(35, 'faq', 'Trained Question', 'General', 'What should I do when I do not know which page to use?', 'Identify what you want to do first, such as reserve a book, request guidance, check a scholarship, or update alumni information. Open the matching service from the dashboard. Ask the responsible office when the correct service is unclear.', 1, '2026-09-23 07:31:19', '2026-09-23 07:40:49'),
(36, 'faq', 'Trained Question', 'General', 'How do I view school announcements?', 'Sign in, open the dashboard navigation, and choose School Announcements. Select an announcement to read its complete details.', 1, '2026-09-23 07:31:20', '2026-09-23 07:40:49'),
(37, 'faq', 'Trained Question', 'General', 'How do I view my profile?', 'Open Profile from the dashboard navigation. Review your account information and edit only the fields that the system allows you to change.', 1, '2026-09-23 07:31:20', '2026-09-23 07:40:49'),
(38, 'faq', 'Trained Question', 'General', 'How do I learn about the system?', 'Open About from the dashboard navigation and review the information about the PASS support system and its services.', 1, '2026-09-23 07:31:20', '2026-09-23 07:40:49'),
(39, 'faq', 'Trained Question', 'General', 'How do I get help?', 'Open Help/About or Email Support from the dashboard. Explain your account role, the service you were using, what you clicked, and the message you saw. Never include your password.', 1, '2026-09-23 07:31:20', '2026-09-23 07:40:49'),
(40, 'faq', 'Trained Question', 'General', 'How do I safely end my session?', 'Finish or save your work, then select Logout from the dashboard navigation. Log in again the next time you need to use the system.', 1, '2026-09-23 07:31:20', '2026-09-23 07:40:50'),
(41, 'faq', 'Trained Question', 'Student', 'Where do students log in?', 'Choose Student Login on the PASS login screen. Enter the email and password registered to your student account, then select Login.', 1, '2026-09-23 07:31:20', '2026-09-23 07:40:50'),
(42, 'faq', 'Trained Question', 'Student', 'Where do students see their dashboard?', 'After signing in, select Student Home. Use the dashboard cards or sidebar to open the service you need.', 1, '2026-09-23 07:31:20', '2026-09-23 07:40:50'),
(43, 'faq', 'Trained Question', 'Student', 'How do I open Guidance as a student?', 'From Student Home, select Guidance Dashboard and choose the Guidance task you need.', 1, '2026-09-23 07:31:21', '2026-09-23 07:31:21'),
(44, 'faq', 'Trained Question', 'Student', 'How do I open Library as a student?', 'From Student Home, select Library Dashboard, then choose catalog, check-in, or another available library function.', 1, '2026-09-23 07:31:21', '2026-09-23 07:31:21'),
(45, 'faq', 'Trained Question', 'Student', 'How do I open Clinic as a student?', 'From Student Home, select Clinic Dashboard and choose the available clinic function.', 1, '2026-09-23 07:31:21', '2026-09-23 07:31:21'),
(46, 'faq', 'Trained Question', 'Student', 'How do I open SSC as a student?', 'From Student Home, select the SSC dashboard and browse the available student council services.', 1, '2026-09-23 07:31:21', '2026-09-23 07:31:21'),
(47, 'faq', 'Trained Question', 'Student', 'How do I open Scholarship as a student?', 'From Student Home, select the Scholarship dashboard and review the available scholarship information or application status.', 1, '2026-09-23 07:31:21', '2026-09-23 07:31:21'),
(48, 'faq', 'Trained Question', 'Student', 'How do I open SSAA as a student?', 'From Student Home, select SSAA Student Home to access student progression and alumni-related services.', 1, '2026-09-23 07:31:21', '2026-09-23 07:31:21'),
(49, 'faq', 'Trained Question', 'Student', 'How do I update my profile?', 'Open Profile, review the editable information, update only the fields available to your account, and save the changes.', 1, '2026-09-23 07:31:22', '2026-09-23 07:31:22'),
(50, 'faq', 'Trained Question', 'Student', 'What should I do if a student page is unavailable?', 'Refresh the page and confirm that you are signed in with the correct student account. Try opening the service again from Student Home. Contact support with the service name and visible error if the problem continues.', 1, '2026-09-23 07:31:22', '2026-09-23 07:40:51'),
(51, 'faq', 'Trained Question', 'Teacher', 'Where do teachers log in?', 'Choose Teacher Login on the PASS login screen. Enter the teacher email and password, then select Login.', 1, '2026-09-23 07:31:22', '2026-09-23 07:40:51'),
(52, 'faq', 'Trained Question', 'Teacher', 'Where do teachers see their dashboard?', 'After signing in, select Teacher Home. Use the sidebar to open the services and tools allowed for your account.', 1, '2026-09-23 07:31:22', '2026-09-23 07:40:52'),
(53, 'faq', 'Trained Question', 'Teacher', 'How do I open Guidance as a teacher?', 'From Teacher Home, select Guidance Dashboard and use the functions allowed for your account.', 1, '2026-09-23 07:31:22', '2026-09-23 07:31:22'),
(54, 'faq', 'Trained Question', 'Teacher', 'How do I open Library as a teacher?', 'From Teacher Home, select Library Dashboard and use the available library functions.', 1, '2026-09-23 07:31:22', '2026-09-23 07:31:22'),
(55, 'faq', 'Trained Question', 'Teacher', 'How do I open Clinic as a teacher?', 'From Teacher Home, select Clinic Dashboard and use the available clinic functions.', 1, '2026-09-23 07:31:23', '2026-09-23 07:31:23'),
(56, 'faq', 'Trained Question', 'Teacher', 'How do I open SSC as a teacher?', 'From Teacher Home, select the SSC dashboard. Additional SSC management pages require the permissions assigned to your teacher account.', 1, '2026-09-23 07:31:23', '2026-09-23 07:31:23'),
(57, 'faq', 'Trained Question', 'Teacher', 'How do I open Scholarship as a teacher?', 'From Teacher Home, select the Scholarship dashboard. Management actions depend on your assigned role and service.', 1, '2026-09-23 07:31:23', '2026-09-23 07:31:23'),
(58, 'faq', 'Trained Question', 'Teacher', 'How do I open SSAA as a teacher?', 'From Teacher Home, select SSAA Student Home when the feature is available to your account.', 1, '2026-09-23 07:31:23', '2026-09-23 07:31:23'),
(59, 'faq', 'Trained Question', 'Teacher', 'How do I update teacher profile information?', 'Open Profile, change the fields available to your account, and save the update.', 1, '2026-09-23 07:31:23', '2026-09-23 07:31:23'),
(60, 'faq', 'Trained Question', 'Teacher', 'Why can I not open a teacher management page?', 'The feature may require a specific service assignment or administrator permission. Check that you are using the correct teacher account, then ask an administrator to review your access.', 1, '2026-09-23 07:31:23', '2026-09-23 07:40:53'),
(61, 'faq', 'Trained Question', 'Guidance', 'Where is the Guidance dashboard?', 'Sign in, open the dashboard service menu, and select Guidance. Choose the Guidance task you need from the available options.', 1, '2026-09-23 07:31:24', '2026-09-23 07:40:53'),
(62, 'faq', 'Trained Question', 'Guidance', 'Where can Guidance staff manage cases?', 'From the Guidance dashboard, open Case Management. Select a case to review, update, or continue its workflow.', 1, '2026-09-23 07:31:24', '2026-09-23 07:40:54'),
(63, 'faq', 'Trained Question', 'Guidance', 'How do I review a Guidance case?', 'Open the case management list, select the case, and use the case review or case details page available for that record.', 1, '2026-09-23 07:31:24', '2026-09-23 07:31:24'),
(64, 'faq', 'Trained Question', 'Guidance', 'What is the case review page for?', 'Use Case Review to check the information and current status of a selected Guidance case before taking the next action.', 1, '2026-09-23 07:31:24', '2026-09-23 07:40:54'),
(65, 'faq', 'Trained Question', 'Guidance', 'What is the case details page for?', 'Use Case Details to view the complete information recorded for the selected Guidance case.', 1, '2026-09-23 07:31:24', '2026-09-23 07:40:54'),
(66, 'faq', 'Trained Question', 'Guidance', 'Where can I request or manage Guidance appointments?', 'Open Guidance Dashboard and choose the appointment function available to your account.', 1, '2026-09-23 07:31:24', '2026-09-23 07:31:24'),
(67, 'faq', 'Trained Question', 'Guidance', 'Where can I request a good moral document?', 'From the Guidance service, choose Good Moral. Complete the request fields, review the information, and submit the request.', 1, '2026-09-23 07:31:25', '2026-09-23 07:40:54'),
(68, 'faq', 'Trained Question', 'Guidance', 'Where can Guidance staff view reports?', 'From the Guidance service menu, select Reports. Choose the available report view and review the displayed information.', 1, '2026-09-23 07:31:25', '2026-09-23 07:40:55'),
(69, 'faq', 'Trained Question', 'Guidance', 'How do I submit a Guidance concern?', 'Open the Guidance dashboard, choose the relevant case or appointment option, provide the required details, and submit the request.', 1, '2026-09-23 07:31:25', '2026-09-23 07:31:25'),
(70, 'faq', 'Trained Question', 'Guidance', 'What should I do if my Guidance case is not updated?', 'Check the case status and details page first. If no update is available, contact the Guidance office and provide the case reference or account information.', 1, '2026-09-23 07:31:25', '2026-09-23 07:31:25'),
(71, 'faq', 'Trained Question', 'Library', 'Where is the librarian dashboard?', 'Authorized library staff should sign in and select Librarian Home from the dashboard service menu.', 1, '2026-09-23 07:31:25', '2026-09-23 07:40:55'),
(72, 'faq', 'Trained Question', 'Library', 'Where can I search for books?', 'Open the Library service and select Catalog. Search by the available book information, then review the matching results.', 1, '2026-09-23 07:31:25', '2026-09-23 07:40:55'),
(73, 'faq', 'Trained Question', 'Library', 'How do I check in at the library?', 'Open the Library service and choose QR Check-in. Follow the instructions on screen and wait for the confirmation that your visit was recorded.', 1, '2026-09-23 07:31:26', '2026-09-23 07:40:55'),
(74, 'faq', 'Trained Question', 'Library', 'Where is the Library QR page?', 'Open the Library service and choose the QR option. Use it when you are checking in at the library.', 1, '2026-09-23 07:31:26', '2026-09-23 07:40:55'),
(75, 'faq', 'Trained Question', 'Library', 'How do I check if a book is available?', 'Open Library Catalog, search for the title or author, and review the availability shown for each copy before requesting or reserving it.', 1, '2026-09-23 07:31:26', '2026-09-23 07:40:56'),
(76, 'faq', 'Trained Question', 'Library', 'Where does library staff manage inventory?', 'Authorized staff should open the Library service and select Inventory. Use the available controls to review or update book records.', 1, '2026-09-23 07:31:26', '2026-09-23 07:40:56'),
(77, 'faq', 'Trained Question', 'Library', 'Where can library staff view reports?', 'From the Library management menu, select Reports and choose the report information you need to review.', 1, '2026-09-23 07:31:26', '2026-09-23 07:40:56'),
(78, 'faq', 'Trained Question', 'Library', 'Where are library settings managed?', 'Authorized library staff should select Settings from the Library management menu, then update only the settings assigned to their role.', 1, '2026-09-23 07:31:26', '2026-09-23 07:40:56'),
(79, 'faq', 'Trained Question', 'Library', 'What should I do if QR check-in fails?', 'Confirm that the QR page is open and that your account is signed in, then retry. Contact library staff if the check-in still fails.', 1, '2026-09-23 07:31:27', '2026-09-23 07:31:27'),
(80, 'faq', 'Trained Question', 'Clinic', 'Where is the nurse dashboard?', 'Authorized clinic staff should sign in and select Nurse Home from the dashboard service menu.', 1, '2026-09-23 07:31:27', '2026-09-23 07:40:57'),
(81, 'faq', 'Trained Question', 'Clinic', 'Where can clinic staff view clinic status?', 'From the Clinic service menu, select Clinic Status and review the current information shown.', 1, '2026-09-23 07:31:27', '2026-09-23 07:40:57'),
(82, 'faq', 'Trained Question', 'Clinic', 'Where are health records managed?', 'Authorized clinic staff should select Health Records from the Clinic management menu and use the search or record controls provided.', 1, '2026-09-23 07:31:27', '2026-09-23 07:40:57'),
(83, 'faq', 'Trained Question', 'Clinic', 'Where are clinic visits recorded?', 'From the Clinic management menu, select Visit Logs to review the clinic visits recorded in the system.', 1, '2026-09-23 07:31:27', '2026-09-23 07:40:57'),
(84, 'faq', 'Trained Question', 'Clinic', 'Where can clinic staff post announcements?', 'From the Clinic management menu, select Announcements, create the post, review its content, and save it.', 1, '2026-09-23 07:31:27', '2026-09-23 07:40:57'),
(85, 'faq', 'Trained Question', 'Clinic', 'Where can clinic staff view analytics?', 'From the Clinic management menu, select Analytics and review the available summaries and trends.', 1, '2026-09-23 07:31:28', '2026-09-23 07:40:58'),
(86, 'faq', 'Trained Question', 'Clinic', 'How do I request clinic assistance?', 'Open the Clinic Dashboard and submit the available clinic visit or assistance request with the required details.', 1, '2026-09-23 07:31:28', '2026-09-23 07:31:28'),
(87, 'faq', 'Trained Question', 'Clinic', 'What information belongs in a clinic request?', 'Enter the information requested by the clinic form and provide an accurate description of your concern.', 1, '2026-09-23 07:31:28', '2026-09-23 07:31:28'),
(88, 'faq', 'Trained Question', 'Clinic', 'Can other users view my health record?', 'Clinic information should be handled through authorized clinic workflows. Do not share private health details in public messages.', 1, '2026-09-23 07:31:28', '2026-09-23 07:31:28'),
(89, 'faq', 'Trained Question', 'Clinic', 'What should I do if a clinic record is incorrect?', 'Contact authorized clinic staff and identify the record and correction needed. Do not create duplicate records unless instructed.', 1, '2026-09-23 07:31:28', '2026-09-23 07:31:28'),
(90, 'faq', 'Trained Question', 'SSC', 'Where is the SSC dashboard?', 'Sign in, open the dashboard service menu, and select SSC. Review the available events, activities, and announcements.', 1, '2026-09-23 07:31:28', '2026-09-23 07:40:58'),
(91, 'faq', 'Trained Question', 'SSC', 'Where can I view SSC events?', 'Open the SSC dashboard and select the events or activities section.', 1, '2026-09-23 07:31:29', '2026-09-23 07:31:29'),
(92, 'faq', 'Trained Question', 'SSC', 'How do I register for an SSC event?', 'Open the event from the SSC dashboard, check that registration is open, complete the requested fields, and submit the registration.', 1, '2026-09-23 07:31:29', '2026-09-23 07:31:29'),
(93, 'faq', 'Trained Question', 'SSC', 'Where can I see SSC announcements?', 'Open SSC from the dashboard and select Announcements, or open School Announcements and review the current SSC posts.', 1, '2026-09-23 07:31:29', '2026-09-23 07:40:59'),
(94, 'faq', 'Trained Question', 'SSC', 'Where does an SSC Head manage events?', 'An authorized SSC/Scholarship Head should select Manage SSC, then open Event Management to add, edit, filter, or review events and announcements.', 1, '2026-09-23 07:31:29', '2026-09-23 07:40:59'),
(95, 'faq', 'Trained Question', 'SSC', 'Where does an SSC Head manage candidates?', 'An authorized SSC/Scholarship Head should select Manage SSC, then open Candidate Management to manage candidates, officers, advisers, and election settings.', 1, '2026-09-23 07:31:29', '2026-09-23 07:40:59'),
(96, 'faq', 'Trained Question', 'SSC', 'Where are SSC reports generated?', 'From Manage SSC, select Reports. Choose the report type, select the format, add a matching letterhead when needed, and select Export Report.', 1, '2026-09-23 07:31:29', '2026-09-23 07:41:00'),
(97, 'faq', 'Trained Question', 'SSC', 'How do I add an SSC announcement?', 'Open the SSC event management page, choose Add Announcement, enter the required information, optionally add supported images, and save.', 1, '2026-09-23 07:31:30', '2026-09-23 07:31:30'),
(98, 'faq', 'Trained Question', 'SSC', 'How do I manage an SSC candidate?', 'Open SSC candidate management, choose the candidate tab, then add or edit the record using the required fields.', 1, '2026-09-23 07:31:30', '2026-09-23 07:31:30'),
(99, 'faq', 'Trained Question', 'SSC', 'Why can I not manage SSC candidates?', 'Candidate management requires the appropriate SSC/Scholarship Head permission. Contact an administrator if your assignment is correct but access is missing.', 1, '2026-09-23 07:31:30', '2026-09-23 07:31:30'),
(100, 'faq', 'Trained Question', 'Scholarship', 'Where is the Scholarship dashboard?', 'Sign in, open the dashboard service menu, and select Scholarship. Review the scholarship information and application status available to your account.', 1, '2026-09-23 07:31:30', '2026-09-23 07:41:00'),
(101, 'faq', 'Trained Question', 'Scholarship', 'Where can I create a scholarship announcement?', 'An authorized user should select Manage Scholarship, open Create Announcement, enter the required title and content, then save the announcement.', 1, '2026-09-23 07:31:30', '2026-09-23 07:41:00'),
(102, 'faq', 'Trained Question', 'Scholarship', 'How do I create a scholarship announcement?', 'Open Create Announcement, enter the title and content, select the program type, add an optional deadline and supported images, then save.', 1, '2026-09-23 07:31:30', '2026-09-23 07:31:30'),
(103, 'faq', 'Trained Question', 'Scholarship', 'What scholarship program types are available?', 'The documented program types are TDP, TES, Financial Aid, and Other. Specify the program name when Other is selected.', 1, '2026-09-23 07:31:31', '2026-09-23 07:31:31'),
(104, 'faq', 'Trained Question', 'Scholarship', 'How do I validate a scholarship submission?', 'Open Document Validation, search for the person, select the scholarship announcement, enter the validation date, add optional notes, and validate the submission.', 1, '2026-09-23 07:31:31', '2026-09-23 07:31:31'),
(105, 'faq', 'Trained Question', 'Scholarship', 'Where can I view scholarship feedback?', 'Open the Feedback tab in the scholarship announcement management page.', 1, '2026-09-23 07:31:31', '2026-09-23 07:31:31'),
(106, 'faq', 'Trained Question', 'Scholarship', 'Where are scholarship reports generated?', 'From Manage Scholarship, select Reports. Choose the report type and format, optionally add the correct letterhead, and select Export Report.', 1, '2026-09-23 07:31:31', '2026-09-23 07:41:01'),
(107, 'faq', 'Trained Question', 'Scholarship', 'What scholarship reports are available?', 'The documented reports are Announcements, Applications, Validations, and General Statistics.', 1, '2026-09-23 07:31:31', '2026-09-23 07:31:31'),
(108, 'faq', 'Trained Question', 'Scholarship', 'What file type is required for a Word letterhead?', 'Use a `.docx` letterhead template for a Word export. Use `.xlsx` for an Excel export.', 1, '2026-09-23 07:31:31', '2026-09-23 07:31:31'),
(109, 'faq', 'Trained Question', 'Scholarship', 'What should I do if a scholarship announcement is not visible?', 'Open View Announcements, check the active tab and page status, refresh the page, and verify that the announcement was saved successfully.', 1, '2026-09-23 07:31:32', '2026-09-23 07:31:32'),
(110, 'faq', 'Trained Question', 'SSAA', 'What is SSAA?', 'SSAA supports student progression, alumni status, graduate information, employment updates, and alumni-related services. Open SSAA from the dashboard and choose the task you need.', 1, '2026-09-23 07:31:32', '2026-09-23 07:41:02'),
(111, 'faq', 'Trained Question', 'SSAA', 'Where is SSAA Student Home?', 'Sign in, open the dashboard service menu, and select SSAA Student Home. Review the available progression and alumni services.', 1, '2026-09-23 07:31:32', '2026-09-23 07:41:02'),
(112, 'faq', 'Trained Question', 'SSAA', 'How do I update alumni information?', 'Open the SSAA or alumni feature available to your account and update the fields provided by the form.', 1, '2026-09-23 07:31:32', '2026-09-23 07:31:32'),
(113, 'faq', 'Trained Question', 'SSAA', 'How do I update employment information?', 'Open the SSAA employment update feature, enter the current employment details requested, and submit the update.', 1, '2026-09-23 07:31:32', '2026-09-23 07:31:32'),
(114, 'faq', 'Trained Question', 'SSAA', 'Where can administrators manage alumni records?', 'An authorized administrator should open the Alumni Management option, search for the record, review the details, and use the available update controls.', 1, '2026-09-23 07:31:32', '2026-09-23 07:41:03'),
(115, 'faq', 'Trained Question', 'SSAA', 'Where can I view graduate information?', 'Open SSAA Student Home or the alumni feature available to your account.', 1, '2026-09-23 07:31:33', '2026-09-23 07:31:33'),
(116, 'faq', 'Trained Question', 'SSAA', 'Can I correct an alumni record?', 'Use the available edit fields in the SSAA or alumni page. Contact the administrator when the field is not editable.', 1, '2026-09-23 07:31:33', '2026-09-23 07:31:33'),
(117, 'faq', 'Trained Question', 'SSAA', 'What should I do if my employment update is not saved?', 'Check the required fields and the save notification, then retry. Contact support with the page and error message if it still fails.', 1, '2026-09-23 07:31:33', '2026-09-23 07:31:33'),
(118, 'faq', 'Trained Question', 'SSAA', 'Who can access alumni management?', 'Alumni management is an administrator function unless the system grants another role access.', 1, '2026-09-23 07:31:33', '2026-09-23 07:31:33'),
(119, 'faq', 'Trained Question', 'SSAA', 'Why is my alumni information incomplete?', 'Review the available profile and employment fields, then submit the missing information. Ask the administrator about fields not shown in the system.', 1, '2026-09-23 07:31:33', '2026-09-23 07:31:33'),
(121, 'faq', 'Trained Question', 'Admin', 'Where can administrators assign services?', 'Open Admin Assignments, search for the user, choose the appropriate role or service assignment, review it carefully, and save the change.', 1, '2026-09-23 07:31:34', '2026-09-23 07:41:04'),
(122, 'faq', 'Trained Question', 'Admin', 'Where can administrators manage users?', 'Open User Management, choose the appropriate tab, search or filter the records, and use the available edit, archive, or removal action.', 1, '2026-09-23 07:31:34', '2026-09-23 07:41:04'),
(123, 'faq', 'Trained Question', 'Admin', 'Where can administrators view analytics?', 'Open Admin Analytics or Reports and Analytics, choose the information you want to review, and use the displayed filters or summaries.', 1, '2026-09-23 07:31:34', '2026-09-23 07:41:04'),
(124, 'faq', 'Trained Question', 'Admin', 'Where is the admin calendar manager?', 'Open Calendar Manager, review the existing entries, and use the available controls to add or update calendar information.', 1, '2026-09-23 07:31:34', '2026-09-23 07:41:04'),
(125, 'faq', 'Trained Question', 'Admin', 'Where are admin settings managed?', 'Open Admin Settings and choose the tab for the setting you want to manage, such as AI Management, login branding, archives, or footer content.', 1, '2026-09-23 07:31:34', '2026-09-23 07:41:04'),
(126, 'faq', 'Trained Question', 'Admin', 'How do I teach the AI agent?', 'Open Admin Settings, select AI Management, open Teach Your Agent, enter the category, user question, and verified answer, then save the training entry.', 1, '2026-09-23 07:31:34', '2026-09-23 07:31:34'),
(127, 'faq', 'Trained Question', 'Admin', 'How do I add AI knowledge?', 'Open Admin Settings, select AI Management, open Knowledge Base, enter a title, category, and verified content, then save the entry.', 1, '2026-09-23 07:31:35', '2026-09-23 07:31:35'),
(128, 'faq', 'Trained Question', 'Admin', 'Where can administrators review feedback?', 'Open Feedback and Ratings, select the relevant service or filter, and review the submitted ratings and comments.', 1, '2026-09-23 07:31:35', '2026-09-23 07:41:05'),
(129, 'faq', 'Trained Question', 'Admin', 'Where can administrators manage student promotion and reports?', 'Open Student Promotion for promotion tasks, or Reports and Analytics for report tasks. Select the appropriate tab, complete the required fields, and review the confirmation after saving or exporting.', 1, '2026-09-23 07:31:35', '2026-09-23 07:41:05'),
(130, 'faq', 'Trained Question', 'Admin', 'Where is the admin dashboard?', 'Sign in with an administrator account, then select Admin Home. Use the sidebar to open the management area you need.', 1, '2026-09-23 07:42:08', '2026-09-23 07:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `ai_settings`
--

CREATE TABLE `ai_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ai_settings`
--

INSERT INTO `ai_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('persona_prompt', 'You are an AI assistant for PASS College\'s student support system. Answer clearly, accurately, professionally, and friendly.\r\n- Use ONLY the provided knowledge base below to answer. Do not use outside knowledge, and do not invent or guess details that are not explicitly present in the knowledge base.\r\n- If the knowledge base does not contain enough information to answer the user\'s question, reply with EXACTLY this token and nothing else: [[NOT_IN_KB]]\r\n- Do not apologize, do not explain why, do not add any other text when the answer is not in the knowledge base — just output the token above so the system can offer sample questions instead.\r\n- If the question is a simple greeting or small talk, respond briefly and warmly without needing the knowledge base.\r\n- Prefer concise actionable guidance over long paragraphs.\r\n- When possible, include the relevant module name and a direct next step.\r\n\r\nSupported modules are: Library, Clinic, Scholarship, Guidance, SSC, and SSAA.', '2026-09-15 00:56:15');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_comments`
--

CREATE TABLE `announcement_comments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_course` varchar(255) DEFAULT NULL,
  `announcement_type` varchar(50) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `comment_text` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement_comments`
--

INSERT INTO `announcement_comments` (`id`, `user_id`, `user_name`, `user_course`, `announcement_type`, `announcement_id`, `comment_text`, `created_at`, `updated_at`) VALUES
(3, 15, 'Har Vie', 'Bachelor of Science in Hospitality Management 3', 'ssc', 6, 'WEW', '2026-05-09 22:33:46', '2026-05-09 22:33:46'),
(4, 12, 'TEACHER AKO ba?', 'Bachelor of Science in Tourism Management', 'ssc', 6, 'OKAY', '2026-05-09 22:34:21', '2026-05-09 22:34:21'),
(5, 15, 'Har Vie', 'Bachelor of Science in Hospitality Management 3', 'ssc', 6, 'YES', '2026-05-09 23:17:10', '2026-05-09 23:17:10'),
(6, 12, 'TEACHER AKO ba?', 'Bachelor of Science in Tourism Management', 'clinic', 2, 'YEW', '2026-05-09 23:23:41', '2026-05-09 23:23:41'),
(7, 12, 'TEACHER AKO ba?', 'Bachelor of Science in Tourism Management', 'scholarship', 2, 'WEW', '2026-05-09 23:33:59', '2026-05-09 23:33:59'),
(8, 65, 'Kurumi Harvie Tokisaki', 'Bachelor of Science in Computer Science 4', 'calendar', 61, 'asa', '2026-07-15 23:47:16', '2026-07-15 23:47:16'),
(9, 65, 'Kurumi Harvie Tokisaki', 'Bachelor of Science in Computer Science 4', 'ssc', 5, 'aa', '2026-07-16 00:07:24', '2026-07-16 00:07:24'),
(10, 46, 'Jasmin Arachea', 'Bachelor of Science in Computer Science 4', 'ssc', 13, 'yehey', '2026-09-10 12:04:40', '2026-09-10 12:04:40');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_likes`
--

CREATE TABLE `announcement_likes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `announcement_type` varchar(50) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement_likes`
--

INSERT INTO `announcement_likes` (`id`, `user_id`, `announcement_type`, `announcement_id`, `created_at`) VALUES
(1, 18, 'ssc', 6, '2026-05-09 02:54:00'),
(3, 12, 'ssc', 6, '2026-05-09 22:33:00'),
(4, 15, 'clinic', 3, '2026-05-09 22:47:09'),
(8, 15, 'clinic', 2, '2026-05-09 23:29:52'),
(35, 12, 'clinic', 3, '2026-05-09 23:46:03'),
(44, 12, 'clinic', 2, '2026-05-10 00:03:58'),
(49, 12, 'scholarship', 2, '2026-05-10 00:18:56'),
(51, 15, 'scholarship', 2, '2026-05-10 00:19:29'),
(57, 15, 'ssc', 6, '2026-05-18 02:43:20'),
(58, 15, 'calendar', 17, '2026-05-20 00:46:38'),
(65, 36, 'calendar', 61, '2026-07-07 01:41:46'),
(66, 16, 'calendar', 61, '2026-07-07 22:57:10'),
(71, 8, 'scholarship', 3, '2026-07-09 00:18:45'),
(72, 65, 'calendar', 61, '2026-07-15 23:46:58'),
(73, 48, 'calendar', 61, '2026-07-15 23:47:07'),
(78, 65, 'scholarship', 3, '2026-07-16 00:13:46'),
(79, 65, 'ssc', 5, '2026-07-16 00:20:16'),
(80, 36, 'calendar', 62, '2026-08-25 09:33:51'),
(81, 69, 'calendar', 62, '2026-09-05 01:43:17'),
(82, 69, 'calendar', 61, '2026-09-05 01:43:19'),
(83, 69, 'calendar', 17, '2026-09-05 01:43:28'),
(84, 46, 'ssc', 13, '2026-09-10 12:04:21');

-- --------------------------------------------------------

--
-- Table structure for table `carousel_images`
--

CREATE TABLE `carousel_images` (
  `id` int(11) NOT NULL,
  `login_type` enum('student','teacher') NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carousel_images`
--

INSERT INTO `carousel_images` (`id`, `login_type`, `image_path`, `display_order`, `created_at`, `updated_at`) VALUES
(6, 'student', 'uploads/carousel/carousel_student_1788511915_904e4f18.jpg', 0, '2026-09-04 08:51:55', '2026-09-04 08:51:55'),
(7, 'student', 'uploads/carousel/carousel_student_1788511915_0287dbdf.jpg', 1, '2026-09-04 08:51:55', '2026-09-04 08:51:55'),
(8, 'student', 'uploads/carousel/carousel_student_1788511997_d4a11f04.jpg', 2, '2026-09-04 08:53:17', '2026-09-04 08:53:17'),
(9, 'teacher', 'uploads/carousel/carousel_teacher_1788512120_c4a180bf.jpg', 0, '2026-09-04 08:55:20', '2026-09-04 08:55:20'),
(10, 'teacher', 'uploads/carousel/carousel_teacher_1788512120_62dc8b2c.jpg', 1, '2026-09-04 08:55:20', '2026-09-04 08:55:20');

-- --------------------------------------------------------

--
-- Table structure for table `case_reported_persons`
--

CREATE TABLE `case_reported_persons` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_id` int(11) NOT NULL,
  `person_type` enum('student','teacher','course','other') NOT NULL DEFAULT 'student',
  `person_name` varchar(150) DEFAULT NULL,
  `person_id` varchar(50) DEFAULT NULL,
  `person_course` varchar(100) DEFAULT NULL,
  `custom_person_type` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `person_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_reported_persons`
--

INSERT INTO `case_reported_persons` (`id`, `case_id`, `person_type`, `person_name`, `person_id`, `person_course`, `custom_person_type`, `created_at`, `person_user_id`) VALUES
(1, 22, 'teacher', 'TEACHER AKOI', '123', '', NULL, '2026-04-19 15:59:11', NULL),
(2, 23, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-19 16:01:01', NULL),
(3, 24, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-19 16:06:48', NULL),
(4, 25, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-19 16:21:53', NULL),
(5, 26, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-19 16:22:47', NULL),
(6, 27, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-19 16:24:25', NULL),
(7, 28, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-19 16:35:59', NULL),
(8, 29, 'teacher', 'TEACHER AKOI', '123', '', NULL, '2026-04-19 16:59:11', NULL),
(9, 29, 'course', 'Bachelor of Science in Computer Science - 2nd Year', '', 'Bachelor of Science in Computer Science / 2nd Year', NULL, '2026-04-20 11:27:58', NULL),
(10, 30, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-20 12:38:01', NULL),
(11, 31, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-20 13:16:31', NULL),
(12, 32, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-20 13:52:56', NULL),
(13, 33, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-20 14:13:24', NULL),
(14, 34, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-20 14:43:04', NULL),
(15, 35, 'teacher', 'TEACHER AKO ba?', '123', 'Bachelor of Science in Tourism Management', NULL, '2026-04-21 09:54:43', 12),
(16, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-21 11:03:01', 15),
(17, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-21 11:47:45', 12),
(18, 36, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-22 08:14:58', 15),
(19, 37, 'teacher', 'TEACHER AKO ba?', '123', 'Bachelor of Science in Tourism Management', NULL, '2026-04-22 08:15:41', 12),
(20, 38, 'teacher', 'TEACHER AKO ba?', '123', 'Bachelor of Science in Tourism Management', NULL, '2026-04-22 11:12:33', 12),
(21, 39, 'teacher', 'TEACHER AKO ba?', '123', 'Bachelor of Science in Tourism Management', NULL, '2026-04-22 11:53:07', 12),
(22, 40, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-04-22 11:59:21', 15),
(23, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 07:58:12', 15),
(24, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:02:02', 15),
(25, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:16:24', 15),
(26, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:30:44', 15),
(27, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:30:56', 15),
(28, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:41:09', 15),
(29, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:44:51', 15),
(30, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:49:10', 15),
(31, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:57:37', 15),
(32, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 08:58:24', 15),
(33, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 09:03:43', 15),
(34, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 09:06:51', 15),
(35, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 09:07:24', 15),
(36, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 09:38:32', 15),
(37, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 09:40:20', 15),
(38, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:24:57', 15),
(39, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:28:23', 15),
(40, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:30:44', 15),
(41, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:34:13', 15),
(42, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:36:31', 15),
(43, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:38:38', 15),
(44, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:39:47', 15),
(45, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:40:39', 15),
(46, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:42:25', 15),
(47, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:43:24', 15),
(48, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:44:58', 15),
(49, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:46:00', 15),
(50, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:51:37', 15),
(51, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 10:53:29', 15),
(52, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:02:36', 15),
(53, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:06:18', 15),
(54, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:08:59', 15),
(55, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:10:01', 15),
(56, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:11:26', 15),
(57, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:13:15', 15),
(58, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:13:52', 15),
(59, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:15:05', 15),
(60, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:16:57', 15),
(61, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:17:57', 15),
(62, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:20:36', 15),
(63, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:22:31', 15),
(64, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-24 12:23:19', 15),
(65, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 06:41:09', 15),
(66, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 06:42:31', 15),
(67, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 06:42:55', 15),
(68, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 06:43:16', 15),
(69, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 06:50:25', 15),
(70, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 06:52:10', 15),
(71, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 06:52:46', 15),
(72, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:11:32', 15),
(73, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:12:11', 15),
(74, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:16:07', 15),
(75, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:26:38', 15),
(76, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:26:43', 15),
(77, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:30:37', 15),
(78, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:34:31', 15),
(79, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:37:18', 15),
(80, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:42:43', 15),
(81, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:46:28', 15),
(82, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:49:46', 15),
(83, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:54:12', 15),
(84, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 07:56:06', 15),
(85, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:00:52', 15),
(86, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:03:48', 15),
(87, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:05:47', 15),
(88, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:09:09', 15),
(89, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:16:28', 15),
(90, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:16:44', 15),
(91, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:22:07', 15),
(92, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:26:15', 15),
(93, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:33:51', 15),
(94, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:46:12', 15),
(95, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:50:47', 15),
(96, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 08:59:25', 15),
(97, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:03:42', 15),
(98, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:05:51', 15),
(99, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:15:58', 15),
(100, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:27:33', 15),
(101, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:28:10', 15),
(102, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:37:33', 15),
(103, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:47:53', 15),
(104, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:53:08', 15),
(105, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:53:17', 15),
(106, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 09:59:42', 15),
(107, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:04:45', 15),
(108, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:12:25', 15),
(109, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:18:54', 15),
(110, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:22:50', 15),
(111, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:26:38', 15),
(112, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:29:35', 15),
(113, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:31:14', 15),
(114, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:45:58', 15),
(115, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:48:42', 15),
(116, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 10:52:39', 15),
(117, 34, 'student', NULL, NULL, NULL, NULL, '2026-04-25 13:23:18', 15),
(118, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 07:09:59', 15),
(119, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 07:16:13', 15),
(120, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 07:23:23', 15),
(121, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 07:23:32', 15),
(122, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 07:33:30', 15),
(123, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:07:41', 15),
(124, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:07:49', 15),
(125, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:12:08', 15),
(126, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:17:19', 15),
(127, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:17:49', 15),
(128, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:20:21', 15),
(129, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:24:35', 15),
(130, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:24:48', 15),
(131, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:28:29', 15),
(132, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-10 09:43:02', 15),
(133, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 08:48:55', 15),
(134, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 08:51:01', 15),
(135, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 08:55:31', 15),
(136, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 09:44:53', 15),
(137, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 09:52:35', 15),
(138, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 09:56:55', 15),
(139, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 10:01:08', 15),
(140, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 10:01:11', 15),
(141, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 10:03:40', 15),
(142, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 10:17:28', 15),
(143, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 10:26:58', 15),
(144, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 10:47:01', 15),
(145, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 10:54:46', 15),
(146, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:00:29', 15),
(147, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:00:37', 15),
(148, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:05:22', 15),
(149, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:07:48', 15),
(150, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:15:19', 15),
(151, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:24:58', 15),
(152, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:32:57', 15),
(153, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:36:25', 15),
(154, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:40:36', 15),
(155, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 11:41:38', 15),
(156, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 12:54:28', 15),
(157, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:00:56', 15),
(158, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:07:15', 15),
(159, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:23:11', 15),
(160, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:23:38', 15),
(161, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:23:49', 15),
(162, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:24:31', 15),
(163, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:28:52', 15),
(164, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-12 13:43:17', 15),
(165, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-14 10:24:11', 15),
(166, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-14 10:37:58', 15),
(167, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-14 10:49:08', 15),
(168, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-14 10:55:35', 15),
(169, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-14 11:17:56', 15),
(170, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-15 07:59:27', 15),
(171, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-15 08:08:34', 15),
(172, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-15 08:13:35', 15),
(173, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-15 08:26:44', 15),
(174, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-15 08:34:01', 15),
(175, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-15 08:51:00', 15),
(176, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-15 09:57:32', 15),
(177, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-16 09:31:58', 15),
(178, 42, 'teacher', 'ako ay walang lobo', '232121212', 'Bachelor of Science in Computer Science Graduate', NULL, '2026-05-16 10:45:19', 11),
(179, 43, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', NULL, '2026-05-16 13:11:01', 15),
(180, 44, 'course', 'Bachelor of Science in Business Administration - 2nd Year', '', 'Bachelor of Science in Business Administration / 2nd Year', NULL, '2026-05-16 13:45:46', NULL),
(181, 45, 'course', 'Bachelor of Science in Business Administration - 1st Year', '', 'Bachelor of Science in Business Administration / 1st Year', NULL, '2026-05-16 13:48:59', 0),
(182, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-16 14:58:17', 15),
(183, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-17 10:23:51', 15),
(184, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-17 10:28:16', 15),
(185, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-17 10:33:29', 15),
(186, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-17 10:50:28', 15),
(187, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-17 11:01:49', 15),
(188, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-17 13:03:51', 15),
(189, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-19 10:37:58', 15),
(190, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-19 12:22:59', 15),
(191, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-20 08:49:27', 15),
(192, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-20 08:51:37', 15),
(193, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-20 10:04:56', 15),
(194, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-20 10:30:19', 15),
(195, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-20 10:34:04', 15),
(196, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 07:39:31', 15),
(197, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 07:40:27', 15),
(198, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 07:43:02', 15),
(199, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 07:46:32', 15),
(200, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 07:48:33', 15),
(201, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 07:53:23', 15),
(202, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 07:56:44', 15),
(203, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-22 09:21:11', 15),
(204, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-27 09:20:01', 15),
(205, 46, 'student', 'Ley Harvie Barreto Peralta', '23-02036', 'Bachelor of Science in Computer Science 3', NULL, '2026-05-28 12:10:38', 36),
(206, 48, 'student', 'Ley Harvie Barreto Peralta', '23-02036', 'Bachelor of Science in Computer Science 3', NULL, '2026-05-28 12:35:30', 36),
(207, 49, 'course', 'Bachelor of Science in Accountancy - 2nd Year', '', 'Bachelor of Science in Accountancy / 2nd Year', NULL, '2026-05-28 13:06:26', 0),
(208, 50, 'course', 'Bachelor of Science in Computer Science - 1st Year', '', 'Bachelor of Science in Computer Science / 1st Year', NULL, '2026-05-28 13:10:34', 0),
(209, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-28 16:42:58', 15),
(210, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-28 17:54:12', 15),
(211, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 17:49:36', 15),
(212, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 18:00:39', 15),
(213, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 18:10:11', 15),
(214, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 18:21:46', 15),
(215, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 18:23:04', 15),
(216, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 18:36:01', 15),
(217, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 18:41:27', 15),
(218, 34, 'student', NULL, NULL, NULL, NULL, '2026-05-29 18:42:07', 15),
(219, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-04 17:14:15', 15),
(220, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-04 17:16:17', 15),
(221, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-06 16:01:10', 15),
(222, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-08 08:15:21', 15),
(223, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-08 08:21:42', 15),
(224, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-08 08:26:03', 15),
(225, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-08 08:27:28', 15),
(226, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-08 08:31:21', 15),
(227, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-08 08:33:57', 15),
(228, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-09 11:46:02', 15),
(229, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-09 11:53:18', 15),
(230, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-09 11:56:18', 15),
(231, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-09 12:05:30', 15),
(232, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-11 08:07:09', 15),
(233, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-11 08:23:13', 15),
(234, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-11 08:25:42', 15),
(235, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-11 08:44:02', 15),
(236, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-11 08:49:07', 15),
(237, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 11:31:43', 15),
(238, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 11:41:19', 15),
(239, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 11:56:50', 15),
(240, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 11:59:52', 15),
(241, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 12:00:03', 15),
(242, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 12:02:56', 15),
(243, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 12:13:57', 15),
(244, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 12:29:18', 15),
(245, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-12 12:35:00', 15),
(246, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-19 16:59:50', 15),
(247, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-19 17:09:41', 15),
(248, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-19 17:11:53', 15),
(249, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 08:35:54', 15),
(250, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 08:43:53', 15),
(251, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 09:00:57', 15),
(252, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 09:01:02', 15),
(253, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 09:06:16', 15),
(254, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 09:50:39', 15),
(255, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 11:13:08', 15),
(256, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-20 12:13:46', 15),
(257, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:26:47', 15),
(258, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:32:47', 15),
(259, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:37:24', 15),
(260, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:43:14', 15),
(261, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:52:23', 15),
(262, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:52:30', 15),
(263, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:56:37', 15),
(264, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 08:56:52', 15),
(265, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 09:00:00', 15),
(266, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 09:02:30', 15),
(267, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 09:02:44', 15),
(268, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 09:08:06', 15),
(269, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 09:12:39', 15),
(270, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 09:36:13', 15),
(271, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 12:36:23', 15),
(272, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 12:37:00', 15),
(273, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 12:37:24', 15),
(274, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 12:39:55', 15),
(275, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 12:42:24', 15),
(276, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 13:20:51', 15),
(277, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 13:20:56', 15),
(278, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 14:01:40', 15),
(279, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-21 14:19:19', 15),
(280, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-23 09:01:50', 15),
(281, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-23 09:07:22', 15),
(282, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-23 09:07:39', 15),
(283, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-23 09:22:49', 15),
(284, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 09:02:38', 15),
(285, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 09:02:44', 15),
(286, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 09:10:24', 15),
(287, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 09:21:28', 15),
(288, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 09:21:48', 15),
(289, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 09:27:55', 15),
(290, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 09:32:40', 15),
(291, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:09:25', 15),
(292, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:10:01', 15),
(293, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:18:32', 15),
(294, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:23:43', 15),
(295, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:25:57', 15),
(296, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:30:54', 15),
(297, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:43:26', 15),
(298, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:50:30', 15),
(299, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:51:27', 15),
(300, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:56:29', 15),
(301, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 10:59:10', 15),
(302, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:05:34', 15),
(303, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:12:11', 15),
(304, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:16:27', 15),
(305, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:23:35', 15),
(306, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:33:52', 15),
(307, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:36:11', 15),
(308, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:36:22', 15),
(309, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:38:12', 15),
(310, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:47:04', 15),
(311, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 11:57:17', 15),
(312, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 12:01:23', 15),
(313, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 12:02:42', 15),
(314, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 12:09:52', 15),
(315, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-25 12:10:35', 15),
(316, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-26 12:38:07', 15),
(317, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-26 12:38:39', 15),
(318, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-27 11:30:46', 15),
(319, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-27 11:35:23', 15),
(320, 34, 'student', NULL, NULL, NULL, NULL, '2026-06-27 11:35:38', 15),
(321, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-02 07:12:44', 15),
(322, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-02 07:17:16', 15),
(323, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-02 07:47:54', 15),
(324, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-02 07:51:53', 15),
(325, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-02 07:53:33', 15),
(326, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-04 07:17:18', 15),
(327, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-07 08:04:33', 15),
(328, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-07 09:49:24', 15),
(329, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-07 09:49:31', 15),
(330, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-07 09:58:06', 15),
(331, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-07 10:01:33', 15),
(332, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-07 10:03:53', 15),
(333, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 07:24:07', 15),
(334, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 07:32:59', 15),
(335, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 07:36:17', 15),
(336, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 07:43:45', 15),
(337, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 07:52:30', 15),
(338, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 07:56:03', 15),
(339, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 07:57:25', 15),
(340, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 08:09:07', 15),
(341, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 08:11:18', 15),
(342, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-08 08:16:23', 15),
(343, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-10 07:44:55', 15),
(344, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-10 08:06:55', 15),
(345, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-13 10:30:25', 15),
(346, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-13 10:31:37', 15),
(347, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-16 07:51:02', 15),
(348, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-16 08:02:01', 15),
(349, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-16 08:07:50', 15),
(350, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-16 08:08:04', 15),
(351, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-16 08:08:07', 15),
(352, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-16 08:17:29', 15),
(353, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-21 06:55:36', 15),
(354, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-21 06:59:10', 15),
(355, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-21 06:59:18', 15),
(356, 34, 'student', NULL, NULL, NULL, NULL, '2026-07-21 07:02:26', 15),
(357, 34, 'student', NULL, NULL, NULL, NULL, '2026-08-16 10:04:22', 15),
(358, 34, 'student', NULL, NULL, NULL, NULL, '2026-08-16 10:12:33', 15),
(359, 34, 'student', NULL, NULL, NULL, NULL, '2026-08-16 10:19:20', 15),
(360, 34, 'student', NULL, NULL, NULL, NULL, '2026-08-16 10:21:45', 15),
(361, 34, 'student', NULL, NULL, NULL, NULL, '2026-08-23 18:36:19', 15),
(362, 34, 'student', NULL, NULL, NULL, NULL, '2026-09-05 14:38:05', 15),
(363, 34, 'student', NULL, NULL, NULL, NULL, '2026-09-16 09:53:53', 15),
(364, 34, 'student', NULL, NULL, NULL, NULL, '2026-09-16 10:43:54', 15),
(365, 34, 'student', NULL, NULL, NULL, NULL, '2026-09-16 10:45:31', 15),
(366, 34, 'student', NULL, NULL, NULL, NULL, '2026-09-16 10:45:51', 15),
(367, 34, 'student', NULL, NULL, NULL, NULL, '2026-09-16 12:06:13', 15);

-- --------------------------------------------------------

--
-- Table structure for table `case_reporters`
--

CREATE TABLE `case_reporters` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_id` int(11) NOT NULL,
  `reporter_type` enum('student','teacher','parent_guardian','course','other','proactive') NOT NULL DEFAULT 'student',
  `reporter_name` varchar(150) DEFAULT NULL,
  `reporter_id` varchar(50) DEFAULT NULL,
  `reporter_course` varchar(100) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `custom_reporter_type` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_reporters`
--

INSERT INTO `case_reporters` (`id`, `case_id`, `reporter_type`, `reporter_name`, `reporter_id`, `reporter_course`, `mobile_number`, `custom_reporter_type`, `created_at`) VALUES
(1, 22, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', '', NULL, '2026-04-19 15:59:11'),
(2, 22, 'course', 'Bachelor in Elementary Education - 1st Year', '', 'Bachelor in Elementary Education / 1st Year', '', NULL, '2026-04-19 15:59:11'),
(3, 23, 'teacher', 'TEACHER AKOI', '123', '', '', NULL, '2026-04-19 16:01:01'),
(4, 23, 'course', 'Bachelor in Elementary Education - 1st Year', '', 'Bachelor in Elementary Education / 1st Year', '', NULL, '2026-04-19 16:01:01'),
(5, 24, 'teacher', 'TEACHER AKOI', '123', '', '', NULL, '2026-04-19 16:06:48'),
(6, 24, 'course', 'Bachelor in Elementary Education - 1st Year', '', 'Bachelor in Elementary Education / 1st Year', '', NULL, '2026-04-19 16:06:48'),
(7, 25, 'teacher', 'TEACHER AKOI', '123', '', '', NULL, '2026-04-19 16:21:53'),
(8, 25, 'course', 'Bachelor in Elementary Education - 1st Year', '', 'Bachelor in Elementary Education / 1st Year', '', NULL, '2026-04-19 16:21:53'),
(9, 26, 'teacher', 'TEACHER AKOI', '123', '', '', NULL, '2026-04-19 16:22:47'),
(10, 26, 'course', 'Bachelor in Elementary Education - 1st Year', '', 'Bachelor in Elementary Education / 1st Year', '', NULL, '2026-04-19 16:22:47'),
(11, 27, 'teacher', 'TEACHER AKOI', '123', '', '', NULL, '2026-04-19 16:24:25'),
(12, 27, 'course', 'Bachelor in Elementary Education - 1st Year', '', 'Bachelor in Elementary Education / 1st Year', '', NULL, '2026-04-19 16:24:25'),
(13, 28, 'teacher', 'TEACHER AKOI', '123', '', '', NULL, '2026-04-19 16:35:59'),
(14, 28, 'course', 'Bachelor in Elementary Education - 1st Year', '', 'Bachelor in Elementary Education / 1st Year', '', NULL, '2026-04-19 16:35:59'),
(18, 29, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', '', NULL, '2026-04-20 10:57:56'),
(20, 29, 'course', 'Bachelor of Science in Business Administration - 1st Year', '', 'Bachelor of Science in Business Administration / 1st Year', '', NULL, '2026-04-20 11:17:17'),
(22, 30, 'course', 'Bachelor of Science in Business Administration - 1st Year', '', 'Bachelor of Science in Business Administration / 1st Year', '', NULL, '2026-04-20 12:38:01'),
(23, 31, 'course', 'Bachelor of Science in Criminology - 2nd Year', '', 'Bachelor of Science in Criminology / 2nd Year', '', NULL, '2026-04-20 13:16:31'),
(24, 32, 'course', 'Bachelor of Science in Computer Science - 3rd Year', '', 'Bachelor of Science in Computer Science / 3rd Year', '', NULL, '2026-04-20 13:52:56'),
(25, 33, 'course', 'Bachelor in Elementary Education - 3rd Year', '', 'Bachelor in Elementary Education / 3rd Year', '', NULL, '2026-04-20 14:13:24'),
(26, 34, 'course', 'Bachelor in Elementary Education - 3rd Year', '', 'Bachelor in Elementary Education / 3rd Year', '', NULL, '2026-04-20 14:43:04'),
(27, 35, 'course', 'Bachelor of Science in Criminology - 2nd Year', '', 'Bachelor of Science in Criminology / 2nd Year', '', NULL, '2026-04-21 09:54:43'),
(28, 36, 'proactive', 'Guidance Counselor', '', '', '', NULL, '2026-04-22 08:14:58'),
(29, 37, 'proactive', 'Guidance Counselor', '', '', '', NULL, '2026-04-22 08:15:41'),
(30, 38, 'course', 'Bachelor of Science in Computer Science - 2nd Year', '', 'Bachelor of Science in Computer Science / 2nd Year', '', NULL, '2026-04-22 11:12:33'),
(31, 39, 'course', 'Bachelor of Science in Criminology - 3rd Year', '', 'Bachelor of Science in Criminology / 3rd Year', '', NULL, '2026-04-22 11:53:07'),
(32, 40, 'proactive', 'Guidance Counselor', '', '', '', NULL, '2026-04-22 11:59:21'),
(33, 42, 'student', 'Har Vie', '23-02044', 'Bachelor of Science in Hospitality Management 3', '', NULL, '2026-05-16 10:45:19'),
(34, 43, 'course', 'Bachelor of Science in Business Administration - 1st Year', '', 'Bachelor of Science in Business Administration / 1st Year', '', NULL, '2026-05-16 13:11:01'),
(35, 44, 'course', 'Bachelor of Science in Accountancy - 2nd Year', '', 'Bachelor of Science in Accountancy / 2nd Year', '', NULL, '2026-05-16 13:45:46'),
(36, 45, 'course', 'Bachelor of Science in Business Administration - 2nd Year', '', 'Bachelor of Science in Business Administration / 2nd Year', '', NULL, '2026-05-16 13:48:59'),
(37, 46, 'course', 'Bachelor in Elementary Education - 2nd Year', '', 'Bachelor in Elementary Education / 2nd Year', '', NULL, '2026-05-28 12:10:38'),
(38, 48, 'course', 'Bachelor in Elementary Education - 3rd Year', '', 'Bachelor in Elementary Education / 3rd Year', '', NULL, '2026-05-28 12:35:30'),
(39, 49, 'course', 'Bachelor in Elementary Education - 2nd Year', '', 'Bachelor in Elementary Education / 2nd Year', '', NULL, '2026-05-28 13:06:26'),
(40, 50, 'course', 'Bachelor of Science in Tourism Management - 3rd Year', '', 'Bachelor of Science in Tourism Management / 3rd Year', '', NULL, '2026-05-28 13:10:34');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_announcements`
--

CREATE TABLE `clinic_announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `target_audience` enum('all','students','teachers','staff') NOT NULL DEFAULT 'all',
  `priority` enum('low','medium','high','emergency') NOT NULL DEFAULT 'medium',
  `duration_days` int(11) DEFAULT 7,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_announcements`
--

INSERT INTO `clinic_announcements` (`id`, `title`, `category`, `description`, `target_audience`, `priority`, `duration_days`, `is_active`, `created_by`, `created_at`, `expires_at`) VALUES
(1, 'FLU', 'health', 'AKDBJKSBDFKJBSDF', 'all', 'medium', 10, 1, 8, '2026-04-03 16:55:42', '2026-04-13 10:55:42'),
(2, 'FLU', 'health', 'Influenza (the flu) is a contagious respiratory illness caused by influenza viruses that infect the nose, throat, and lungs. It causes sudden, severe symptoms—fever, aches, fatigue, and cough—distinct from a cold. Spread through droplets, it ranges from mild to severe and is best prevented by annual vaccination', 'all', 'medium', 7, 1, 8, '2026-05-06 08:13:29', '2026-05-13 08:13:29'),
(3, 'FLU', 'health', 'Influenza (the flu) is a contagious respiratory illness caused by influenza viruses that infect the nose, throat, and lungs. It causes sudden, severe symptoms—fever, aches, fatigue, and cough—distinct from a cold. Spread through droplets, it ranges from mild to severe and is best prevented by annual vaccination', 'all', 'medium', 7, 1, 8, '2026-05-06 08:40:40', '2026-05-13 08:40:40');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_clearance_requests`
--

CREATE TABLE `clinic_clearance_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `request_type` varchar(100) NOT NULL,
  `purpose` text NOT NULL,
  `documents_uploaded` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','requires_more_info','cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `clearance_expiry` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_feedback`
--

CREATE TABLE `clinic_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL,
  `service_quality` int(11) DEFAULT NULL CHECK (`service_quality` >= 1 and `service_quality` <= 5),
  `staff_attitude` int(11) DEFAULT NULL CHECK (`staff_attitude` >= 1 and `staff_attitude` <= 5),
  `waiting_time` int(11) DEFAULT NULL CHECK (`waiting_time` >= 1 and `waiting_time` <= 5),
  `facility_cleanliness` int(11) DEFAULT NULL CHECK (`facility_cleanliness` >= 1 and `facility_cleanliness` <= 5),
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `moderated_by` int(10) UNSIGNED DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_feedback`
--

INSERT INTO `clinic_feedback` (`id`, `user_id`, `visit_id`, `rating`, `comments`, `service_quality`, `staff_attitude`, `waiting_time`, `facility_cleanliness`, `is_approved`, `moderated_by`, `moderated_at`, `created_at`) VALUES
(1, 36, NULL, 4, 'yyyyy', NULL, NULL, NULL, NULL, 0, NULL, NULL, '2026-06-06 14:13:48');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_health_data`
--

CREATE TABLE `clinic_health_data` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `record_date` date NOT NULL,
  `sleep_hours` decimal(3,1) DEFAULT NULL,
  `water_intake_liters` decimal(3,1) DEFAULT NULL,
  `height_cm` decimal(5,1) DEFAULT NULL,
  `weight_kg` decimal(5,1) DEFAULT NULL,
  `mood` varchar(50) DEFAULT NULL,
  `bmi` decimal(4,1) DEFAULT NULL,
  `health_status` varchar(100) DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_health_records`
--

CREATE TABLE `clinic_health_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_medicines`
--

CREATE TABLE `clinic_medicines` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) NOT NULL DEFAULT 5,
  `unit` varchar(50) DEFAULT 'tablets',
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_medicines`
--

INSERT INTO `clinic_medicines` (`id`, `name`, `generic_name`, `description`, `category`, `stock_quantity`, `min_stock_level`, `unit`, `expiry_date`, `batch_number`, `location`, `created_at`, `updated_at`) VALUES
(4, 'RRR', '', '', 'PAYN RELEBER', 21, 80, 'tablets', '2026-04-05', '', '', '2026-04-03 14:13:57', '2026-04-14 08:51:07'),
(5, 'RRRAASSASA', '', 'GDFGDFG', 'PAYN RELEBER', 5, 10, 'tablets', '2026-04-29', '4', '343', '2026-04-03 15:47:28', '2026-04-03 15:47:28'),
(6, 'raytmed', '', '', 'PAYN RELEBER', 5, 5, 'tablets', '2026-05-17', '0325', '', '2026-05-08 09:14:08', '2026-05-08 09:14:08'),
(7, 'Biogesic', 'Paracetamol', 'Used for relief of mild to moderate pain and fever.', 'Analgesic', 100, 5, 'tablets', '2027-12-30', 'BN10293', 'Cabinet A, Shelf 1', '2026-05-22 12:47:43', '2026-05-22 12:47:43'),
(8, 'Neozep Forte', 'Phenylephrine HCl + Chlorpheniramine Maleate + ParacetamoL', 'For relief of clogged nose, runny nose, and headache from common cold.', 'Cough & Cold |', 150, 6, 'tablets', '2027-05-10', 'NZ-4421', 'Cabinet A, Shelf 1', '2026-05-22 12:49:04', '2026-05-22 12:49:04'),
(9, 'Amoxil', 'Amoxicillin', 'Broad-spectrum antibiotic for bacterial infections.', 'Antibiotic', 50, 4, 'Capsules', '2027-02-22', 'AMX-990', 'Cabinet B, Shelf 2', '2026-05-22 12:50:33', '2026-05-22 12:50:33'),
(10, 'Advil', 'Ibuprofen', 'Fast relief for migraines, muscle aches, and dysmenorrhea.', 'NSAID', 80, 2, 'softgels', '2027-07-26', 'ADV-772', 'Cabinet A, Shelf 2', '2026-05-22 12:51:44', '2026-05-22 12:51:44'),
(11, 'Kremil-S', 'Aluminum Hydroxide + Magnesium Hydroxide + Simethicone', 'Relief for stomach hyperacidity and heartburn.', 'Antacid', 200, 5, 'tablets', '2026-12-15', '', 'Cabinet C, Shelf 1', '2026-05-22 12:52:35', '2026-05-22 12:52:35'),
(12, 'Solmux', 'Carbocisteine', 'Used to treat cough with excessive phlegm.', 'Mucolytic', 70, 3, 'capsules', '2026-11-19', 'SM-556', 'Cabinet A, Shelf 3', '2026-05-22 12:54:01', '2026-05-22 12:54:01'),
(13, 'Alaxan FR', 'Ibuprofen + Paracetamol', 'For relief of body aches, pains, and inflammation.', 'Analgesic', 50, 5, 'capsules', '2028-06-22', 'ALX-009', 'Cabinet A, Shelf 2', '2026-05-22 12:55:15', '2026-05-22 12:55:15'),
(14, 'Zyrtec', 'Cetirizine HCl', 'Relief of symptoms associated with allergic rhinitis and hives.', 'Antihistamine', 60, 15, 'tablets', '2027-06-24', 'ZRT-223', 'Cabinet B, Shelf 1', '2026-05-22 12:56:18', '2026-05-22 12:56:18'),
(15, 'Dulcolax', 'Bisacodyl', 'Relief of constipation and irregularity.', 'Laxative', 40, 5, 'tablets', '2027-06-23', 'DLX-482', 'Cabinet C, Shelf 2', '2026-05-22 12:57:33', '2026-05-22 12:57:33'),
(16, 'Ascof Forte', 'Vitex Negundo L.', 'Natural cough remedy from Lagundi leaves.', 'Herbal / Anti-asthmatic', 40, 5, 'tablets', '2027-06-24', 'LGN-331', 'Cabinet A, Shelf 3', '2026-05-22 12:59:07', '2026-05-22 12:59:07'),
(17, 'Buscopan Venus', 'Hyoscine N-butylbromide + Paracetamol', 'Targeted relief for severe abdominal pain and menstrual cramps.', 'Antispasmodic', 30, 2, 'tablets', '2027-03-17', 'BCP-811', 'Cabinet B, Shelf 2', '2026-05-22 13:00:53', '2026-05-22 13:00:53'),
(18, 'Diatabs', 'Loperamide', 'Fast relief for diarrhea and loose bowel movement.', 'Antidiarrheal', 40, 10, 'capsules', '2027-06-16', 'DTB-002', 'Cabinet C, Shelf 1', '2026-05-22 13:02:38', '2026-05-22 13:02:38'),
(19, 'Enervon', 'Vitamin B-Complex + Vitamin C', 'Daily multivitamin to boost the immune system.', 'Vitamin/Supplement', 30, 3, 'tablets', '2028-09-24', 'ENV-902', 'Cabinet D, Shelf 1', '2026-05-22 13:03:50', '2026-05-22 13:03:50'),
(20, 'Flanax', 'Naproxen Sodium', 'Used for severe headaches and toothaches.', 'NSAID', 55, 5, 'tablets', '2027-08-26', 'MED-661', 'Cabinet A, Shelf 1', '2026-05-22 13:05:07', '2026-05-22 13:05:07'),
(21, 'Bactidol', 'Hexetidine', 'Gargle solution for sore throat and mouth sores.', 'Oral Antiseptic', 20, 2, 'bottles', '2027-01-20', 'BCT-445', 'Cabinet A, Shelf 1', '2026-05-22 13:06:21', '2026-05-22 13:06:21'),
(22, 'Gaviscon', 'Sodium Alginate + Sodium Bicarbonate', 'Vaporizing ointment for relief of nasal congestion and muscle aches.', 'Antacid', 30, 5, 'sachets', '2027-05-22', 'GAV-092', 'Cabinet A, Shelf 2', '2026-05-22 13:08:01', '2026-05-22 13:08:01'),
(23, 'Poten-Cee', 'Ascorbic Acid', 'Sugar-coated Vitamin C for daily immunity.', 'Vitamin C', 100, 5, 'tablets', '2028-08-17', 'PTC-111', 'Cabinet D, Shelf 2', '2026-05-22 13:09:18', '2026-05-22 13:09:18');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_medicine_batches`
--

CREATE TABLE `clinic_medicine_batches` (
  `id` int(10) UNSIGNED NOT NULL,
  `medicine_id` int(10) UNSIGNED NOT NULL,
  `batch_number` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_medicine_batches`
--

INSERT INTO `clinic_medicine_batches` (`id`, `medicine_id`, `batch_number`, `quantity`, `expiry_date`, `location`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 10, 'A', 43, '2026-07-01', 'DD', 1, '2026-06-03 11:40:03', '2026-06-03 11:40:03');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_medicine_categories`
--

CREATE TABLE `clinic_medicine_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_medicine_categories`
--

INSERT INTO `clinic_medicine_categories` (`id`, `name`, `created_at`) VALUES
(1, 'PAYN RELEBER', '2026-04-03 14:01:47'),
(5, 'Antibiotic', '2026-04-03 15:47:52'),
(12, 'Analgesic', '2026-05-22 12:41:51'),
(13, 'Cough & Cold |', '2026-05-22 12:42:06'),
(14, 'NSAID', '2026-05-22 12:42:35'),
(15, 'Antacid', '2026-05-22 12:42:45'),
(16, 'Mucolytic', '2026-05-22 12:42:56'),
(17, 'Antipyretic (Kids)', '2026-05-22 12:43:37'),
(18, 'Antihistamine', '2026-05-22 12:43:45'),
(19, 'Laxative', '2026-05-22 12:43:55'),
(20, 'Herbal / Anti-asthmatic', '2026-05-22 12:44:22'),
(21, 'Antispasmodic', '2026-05-22 12:44:35'),
(22, 'Antidiarrheal', '2026-05-22 12:44:45'),
(23, 'Vitamin/Supplement', '2026-05-22 12:44:58'),
(24, 'Oral Antiseptic', '2026-05-22 12:45:28'),
(25, 'Topical Ointment', '2026-05-22 12:46:05'),
(26, 'Vitamin C', '2026-05-22 12:46:15');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_status`
--

CREATE TABLE `clinic_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `duration_hours` int(11) DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nurse_back_date` date DEFAULT NULL,
  `nurse_back_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_status`
--

INSERT INTO `clinic_status` (`id`, `is_available`, `reason`, `duration_hours`, `updated_by`, `updated_at`, `nurse_back_date`, `nurse_back_time`) VALUES
(1, 1, NULL, NULL, NULL, '2026-04-03 09:53:17', NULL, NULL),
(2, 1, '', NULL, 8, '2026-04-03 12:32:14', NULL, NULL),
(3, 1, '', NULL, 8, '2026-04-03 12:32:25', NULL, NULL),
(4, 0, '', NULL, 8, '2026-04-03 12:36:57', NULL, NULL),
(5, 1, '', NULL, 8, '2026-04-10 15:32:11', NULL, NULL),
(6, 0, 'anana', NULL, 8, '2026-05-06 20:50:40', NULL, NULL),
(7, 0, 'Lunch Break', NULL, 8, '2026-05-28 13:19:46', NULL, NULL),
(8, 1, 'Lunch Break', NULL, 8, '2026-05-30 14:48:03', NULL, NULL),
(9, 0, 'Lunch Break', NULL, 8, '2026-06-05 13:40:42', NULL, NULL),
(10, 0, 'Lunch Break', NULL, 8, '2026-06-08 08:42:23', NULL, '10:42:00'),
(11, 1, 'Lunch Break', NULL, 8, '2026-06-08 08:42:28', NULL, '10:42:00'),
(12, 0, 'Lunch Break', NULL, 8, '2026-06-08 08:42:41', NULL, '10:42:00'),
(13, 0, 'Lunch Break', NULL, 8, '2026-06-08 08:45:33', NULL, '10:42:00'),
(14, 0, 'Lunch Break', NULL, 8, '2026-06-08 08:45:37', NULL, '10:42:00'),
(15, 0, 'Lunch Break', NULL, 8, '2026-06-08 08:46:05', NULL, '10:42:00'),
(16, 1, 'Lunch Break', NULL, 8, '2026-06-08 08:46:10', NULL, '10:42:00'),
(17, 0, 'Lunch Break', NULL, 8, '2026-06-08 08:46:24', NULL, '14:42:00'),
(18, 0, 'Lunch Break', NULL, 8, '2026-06-08 09:01:44', NULL, NULL),
(19, 0, 'Lunch Break', 3, 8, '2026-06-08 09:02:27', NULL, NULL),
(20, 0, 'Lunch Break', 3, 8, '2026-06-08 09:05:50', NULL, NULL),
(21, 0, 'Lunch Break', NULL, 8, '2026-06-08 09:05:56', '2026-06-23', NULL),
(22, 0, 'Lunch Break', NULL, 8, '2026-06-08 09:06:34', NULL, '12:06:00'),
(23, 0, 'Lunch Break', 3, 8, '2026-06-08 09:06:51', NULL, '12:06:00'),
(24, 0, 'Lunch Break', NULL, 8, '2026-06-08 09:07:02', NULL, '12:06:00'),
(25, 0, 'Lunch Break', NULL, 8, '2026-06-08 09:09:17', NULL, '12:06:00'),
(26, 0, 'Lunch Break', NULL, 8, '2026-06-08 09:11:45', NULL, '12:06:00'),
(27, 1, 'Lunch Break', NULL, 8, '2026-07-16 08:03:42', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clinic_treatments`
--

CREATE TABLE `clinic_treatments` (
  `id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_provided` text DEFAULT NULL,
  `medicines_given` text DEFAULT NULL,
  `follow_up_instructions` text DEFAULT NULL,
  `referred_to_specialist` tinyint(1) DEFAULT 0,
  `specialist_referral` text DEFAULT NULL,
  `treated_by` int(10) UNSIGNED DEFAULT NULL,
  `treatment_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_visits`
--

CREATE TABLE `clinic_visits` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `visitor_type` enum('student','teacher','visitor') NOT NULL DEFAULT 'visitor',
  `student_number` varchar(50) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `course_department` varchar(150) DEFAULT NULL,
  `reason_for_visit` varchar(255) DEFAULT NULL,
  `checkin_method` varchar(50) NOT NULL DEFAULT 'qr',
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `status` enum('waiting','in_consultation','pending_logout','completed') NOT NULL DEFAULT 'waiting',
  `priority` enum('low','medium','high','emergency') NOT NULL DEFAULT 'medium',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  `completion_status` enum('pending','completed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_visits`
--

INSERT INTO `clinic_visits` (`id`, `user_id`, `name`, `visitor_type`, `student_number`, `employee_id`, `course_department`, `reason_for_visit`, `checkin_method`, `time_in`, `time_out`, `duration_minutes`, `status`, `priority`, `created_at`, `remarks`, `completion_status`) VALUES
(1, NULL, 'asdasda', 'student', NULL, NULL, 'dasd', 'dasda', 'manual', '2026-04-03 12:09:55', '2026-04-03 12:09:55', 0, 'completed', 'low', '2026-04-03 18:09:55', NULL, 'pending'),
(2, 11, 'ako ay walang lobo', 'student', NULL, NULL, 'Bachelor of Science in Computer Science 2', 'MASAKIT ANG KANYANG TET', 'search_autofill', '2026-04-04 02:09:00', '2026-04-04 02:09:00', 0, 'completed', 'low', '2026-04-04 08:09:00', NULL, 'pending'),
(3, 14, 'Ley Harvie B Peralta', 'student', NULL, NULL, 'Bachelor of Science in Computer Science Graduate', 'Fever', 'search_autofill', '2026-04-14 09:04:41', '2026-04-14 09:04:41', 0, 'completed', 'low', '2026-04-14 09:04:41', NULL, 'pending'),
(4, 15, 'Har Vie', 'student', NULL, NULL, 'Bachelor of Science in Hospitality Management 3', 'Stomach Pain', 'search_autofill', '2026-05-06 20:54:10', '2026-05-06 20:54:10', 0, 'completed', 'low', '2026-05-06 20:54:10', NULL, 'pending'),
(5, 15, 'Har Vie', 'student', NULL, NULL, 'Bachelor of Science in Hospitality Management 3', 'Allergies', 'search_autofill', '2026-05-08 09:14:32', '2026-05-08 09:14:32', 0, 'completed', 'low', '2026-05-08 09:14:32', NULL, 'pending'),
(6, 36, 'Ley Harvie Barreto Peralta', 'student', NULL, NULL, 'Bachelor of Science in Computer Science 3', 'Common Cold', 'search_autofill', '2026-05-30 14:51:00', '2026-05-30 14:51:00', 0, 'completed', 'low', '2026-05-30 14:51:00', NULL, 'pending'),
(7, NULL, 'Ley Harvie B Peralta', 'student', NULL, NULL, 'Bachelor of Science in Computer Science 2', 'Stomach Pain', 'manual', '2026-05-31 10:25:41', '2026-05-31 10:25:41', 0, 'completed', 'low', '2026-05-31 10:25:41', 'Given Medication / Rested', 'completed'),
(8, 36, 'Ley Harvie Barreto Peralta', 'student', NULL, NULL, 'Bachelor of Science in Computer Science 3', 'Common Cold', 'search_autofill', '2026-06-08 09:40:00', '2026-06-08 09:40:00', 0, 'completed', 'low', '2026-06-08 09:40:00', 'Given Medication / Rested', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `good_moral_feedback`
--

CREATE TABLE `good_moral_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `service_rating` tinyint(3) UNSIGNED NOT NULL CHECK (`service_rating` between 1 and 5),
  `feedback_text` longtext NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `good_moral_feedback`
--

INSERT INTO `good_moral_feedback` (`id`, `student_id`, `service_rating`, `feedback_text`, `created_at`, `updated_at`) VALUES
(1, 36, 4, 'AAA', '2026-06-06 12:55:36', '2026-06-06 12:58:32');

-- --------------------------------------------------------

--
-- Table structure for table `good_moral_records`
--

CREATE TABLE `good_moral_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('eligible','flagged','pending') NOT NULL DEFAULT 'pending',
  `notification_status` enum('none','approved','hold') NOT NULL DEFAULT 'none',
  `notification_sent_at` datetime DEFAULT NULL,
  `notification_read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `good_moral_records`
--

INSERT INTO `good_moral_records` (`id`, `student_id`, `issued_by`, `status`, `notification_status`, `notification_sent_at`, `notification_read_at`, `created_at`, `updated_at`) VALUES
(3, 36, 16, 'pending', 'approved', '2026-06-23 08:10:28', '2026-06-24 08:51:34', '2026-05-28 12:40:18', '2026-06-25 10:26:16'),
(42, 69, NULL, 'pending', 'none', NULL, NULL, '2026-09-05 09:42:54', '2026-09-05 09:42:54');

-- --------------------------------------------------------

--
-- Table structure for table `guidance_cases`
--

CREATE TABLE `guidance_cases` (
  `id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `created_by` int(11) NOT NULL,
  `reported_student_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `counselor_id` int(11) DEFAULT NULL,
  `case_type` varchar(100) NOT NULL,
  `case_topic` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','in_progress','escalated','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `reported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `assigned_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reporter_user_id` int(10) UNSIGNED DEFAULT NULL,
  `reporter_role` enum('student','teacher') NOT NULL DEFAULT 'student',
  `report_type` enum('student_report','teacher_report','self_request','other') NOT NULL DEFAULT 'student_report',
  `type_of_concern` varchar(100) NOT NULL,
  `course_year` varchar(100) DEFAULT NULL,
  `incident_description` text NOT NULL,
  `parent_guardian_notified` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_awareness_required` tinyint(1) NOT NULL DEFAULT 0,
  `external_resolution` enum('none','counseling_completed_follow_up','advised_behavioral_improvement','under_monitoring','case_resolved','scheduled_for_further_sessions') NOT NULL DEFAULT 'none',
  `internal_resolution` enum('none','with_reformation','without_reformation','with_warning','under_monitoring','escalated_case','ongoing_case','pending_case','other','closed_case') NOT NULL DEFAULT 'none',
  `internal_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `priority_level` enum('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
  `participants` enum('STUDENT ONLY','TEACHER ONLY','TEACHER AND STUDENT','Student and Parent/Guardian') DEFAULT 'STUDENT ONLY',
  `case_remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guidance_cases`
--

INSERT INTO `guidance_cases` (`id`, `case_number`, `created_by`, `reported_student_id`, `teacher_id`, `counselor_id`, `case_type`, `case_topic`, `description`, `status`, `priority`, `reported_at`, `assigned_at`, `resolved_at`, `updated_at`, `reporter_user_id`, `reporter_role`, `report_type`, `type_of_concern`, `course_year`, `incident_description`, `parent_guardian_notified`, `teacher_awareness_required`, `external_resolution`, `internal_resolution`, `internal_notes`, `created_at`, `priority_level`, `participants`, `case_remarks`) VALUES
(12, 'G-20260418-AD3E86', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-04-18 12:23:57', NULL, NULL, '2026-04-18 12:32:25', 15, 'student', 'self_request', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', ',,', 0, 0, 'none', 'none', 'Rejected by counselor after review.', '2026-04-18 12:23:57', 'Medium', 'STUDENT ONLY', NULL),
(13, 'G-20260418-5A3971', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-04-18 12:32:10', NULL, NULL, '2026-04-20 13:28:43', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'Duplicate Request', '2026-04-18 12:32:10', 'Medium', 'STUDENT ONLY', NULL),
(14, 'G-20260418-42D0F2', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-04-18 12:32:53', NULL, NULL, '2026-04-20 13:28:38', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'Non-Counseling Needs', '2026-04-18 12:32:53', 'Medium', 'STUDENT ONLY', NULL),
(15, 'G-20260418-12CC88', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-04-18 12:33:25', NULL, NULL, '2026-04-18 13:08:34', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'aa', '2026-04-18 12:33:25', 'Medium', 'STUDENT ONLY', NULL),
(16, 'G-20260418-7E75E3', 0, 15, NULL, 16, '', '', '', '', 'medium', '2026-04-18 12:47:34', NULL, NULL, '2026-06-23 09:32:56', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'case_resolved', 'none', 'OKAY', '2026-04-18 12:47:34', 'Medium', 'STUDENT ONLY', NULL),
(17, 'G-20260418-AACC05', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-04-18 12:49:26', NULL, NULL, '2026-04-18 14:07:10', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'Outside Guidance Scope | Additional note: dont know', '2026-04-18 12:49:26', 'Medium', 'STUDENT ONLY', NULL),
(18, 'G-20260418-98C261', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-04-18 12:49:56', NULL, NULL, '2026-04-20 13:28:30', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'Outside Guidance Scope | Additional note: K', '2026-04-18 12:49:56', 'Medium', 'STUDENT ONLY', NULL),
(19, 'G-20260418-5EA753', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-04-18 12:53:57', NULL, NULL, '2026-04-18 13:24:29', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'Outside Guidance Scope', '2026-04-18 12:53:57', 'Medium', 'STUDENT ONLY', NULL),
(20, 'G-20260418-80A776', 0, 15, NULL, NULL, '', '', '', '', 'medium', '2026-04-18 12:54:08', NULL, NULL, '2026-04-18 13:05:37', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'aa', '2026-04-18 12:54:08', 'Medium', 'STUDENT ONLY', NULL),
(21, 'G-20260418-2DF93E', 0, 15, NULL, NULL, '', '', '', '', 'medium', '2026-04-18 12:58:19', NULL, NULL, '2026-04-18 13:05:25', 15, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'ww', 0, 0, 'none', 'none', 'aaaaa', '2026-04-18 12:58:19', 'Medium', 'STUDENT ONLY', NULL),
(22, 'G-20260419-DC446B', 0, 123, NULL, 16, '', '', '', '', 'medium', '2026-04-19 15:59:11', NULL, NULL, '2026-06-23 09:33:04', 16, 'teacher', 'other', 'Peer Relationship', '', 'DINAMIGAY NG SIOMAI', 0, 0, 'case_resolved', 'none', '.', '2026-04-19 15:59:11', 'Emergency', 'STUDENT ONLY', NULL),
(23, 'G-20260419-5D9B83', 0, 23, NULL, 16, '', '', '', '', 'medium', '2026-04-19 16:01:01', NULL, NULL, '2026-06-23 09:58:47', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'SSSSSSSSSSSS', 0, 0, 'case_resolved', 'none', 'Reporter Type: course | Priority: Emergency | SFFFFFFFFFFFFFFFF', '2026-04-19 16:01:01', 'Emergency', 'STUDENT ONLY', NULL),
(24, 'G-20260419-CF198B', 0, 23, NULL, 16, '', '', '', '', 'medium', '2026-04-19 16:06:48', NULL, NULL, '2026-06-23 09:58:47', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'SSSSSSSSSSSS', 0, 0, 'case_resolved', 'none', 'Reporter Type: course | Priority: Emergency | SFFFFFFFFFFFFFFFF', '2026-04-19 16:06:48', 'Emergency', 'STUDENT ONLY', NULL),
(25, 'G-20260419-880067', 0, 23, NULL, 16, '', '', '', '', 'medium', '2026-04-19 16:21:53', NULL, NULL, '2026-06-23 09:48:24', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'SSSSSSSSSSSS', 0, 0, 'case_resolved', 'none', 'SFFFFFFFFFFFFFFFF', '2026-04-19 16:21:53', 'Emergency', 'STUDENT ONLY', NULL),
(26, 'G-20260419-906C19', 0, 23, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-19 16:22:47', NULL, NULL, '2026-04-20 13:14:04', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'SSSSSSSSSSSS', 0, 0, 'case_resolved', 'none', 'Reporter Type: course | Priority: Emergency | SFFFFFFFFFFFFFFFF', '2026-04-19 16:22:47', 'Emergency', 'STUDENT ONLY', NULL),
(27, 'G-20260419-685096', 0, 23, NULL, 16, '', '', '', 'closed', 'medium', '2026-04-19 16:24:25', NULL, NULL, '2026-04-20 13:14:33', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'SSSSSSSSSSSS', 0, 0, 'case_resolved', 'closed_case', 'Reporter Type: course | Priority: Emergency | SFFFFFFFFFFFFFFFF', '2026-04-19 16:24:25', 'Emergency', 'STUDENT ONLY', NULL),
(28, 'G-20260419-3B72E5', 0, 23, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-19 16:35:59', NULL, NULL, '2026-04-20 13:13:41', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'SSSSSSSSSSSS', 0, 0, 'case_resolved', 'none', 'SFFFFFFFFFFFFFFFF', '2026-04-19 16:35:59', 'Emergency', 'STUDENT ONLY', NULL),
(29, 'G-20260419-4EBBD5', 0, 123, NULL, 16, '', '', '', 'closed', 'medium', '2026-04-19 16:59:11', NULL, NULL, '2026-04-20 13:14:45', 16, 'teacher', 'other', 'Stress Management', '', 'aaaaaaaaaaaaaaS', 0, 0, 'none', 'closed_case', 'aaaaaaaaaaaaaaa', '2026-04-19 16:59:11', 'Medium', 'STUDENT ONLY', NULL),
(30, 'G-20260420-ED2108', 0, 23, NULL, 16, '', '', '', 'closed', 'medium', '2026-04-20 12:38:01', NULL, NULL, '2026-04-20 13:15:28', 16, 'teacher', 'other', 'Health Issue', 'Bachelor of Science in Hospitality Management 3', 'dddddddddd', 0, 0, 'case_resolved', 'closed_case', 'F', '2026-04-20 12:38:01', 'Low', 'STUDENT ONLY', NULL),
(31, 'G-20260420-C01EED', 0, 23, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-20 13:16:31', NULL, NULL, '2026-04-20 13:30:22', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'ZZZZZZZZZZZZZZZZZZZZ', 0, 0, 'case_resolved', 'none', 'XXXXXXXXX', '2026-04-20 13:16:31', 'Emergency', 'STUDENT ONLY', NULL),
(32, 'G-20260420-465FF8', 0, 23, NULL, 16, '', '', '', 'closed', 'medium', '2026-04-20 13:52:56', NULL, NULL, '2026-04-20 14:12:35', 16, 'teacher', 'other', 'Financial Concern', 'Bachelor of Science in Hospitality Management 3', 'AAAAAAAAAAAASASSSSSSSSSSSSSSS', 0, 0, 'none', 'closed_case', 'a', '2026-04-20 13:52:56', 'Medium', 'STUDENT ONLY', NULL),
(33, 'G-20260420-7B2E50', 0, 23, NULL, 16, '', '', '', 'closed', 'medium', '2026-04-20 14:13:24', NULL, NULL, '2026-04-20 15:50:45', 16, 'teacher', 'other', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'a', 0, 0, 'none', 'closed_case', 'a', '2026-04-20 14:13:24', 'High', 'STUDENT ONLY', NULL),
(34, 'G-20260420-C6F416', 0, 23, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-20 14:43:04', NULL, NULL, '2026-04-22 08:14:22', 16, 'teacher', 'other', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'a', 0, 0, 'case_resolved', 'none', 'a', '2026-04-20 14:43:04', 'High', 'TEACHER ONLY', NULL),
(35, 'G-20260421-24F536', 0, 12, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-21 09:54:43', NULL, NULL, '2026-04-22 08:14:16', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Tourism Management', 'xxxxxx', 0, 0, 'case_resolved', 'none', 'dddddd', '2026-04-21 09:54:43', 'Low', 'STUDENT ONLY', NULL),
(36, 'G-20260422-298839', 0, 15, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-22 08:14:58', NULL, NULL, '2026-04-22 11:37:06', 16, 'teacher', 'other', 'Stress Management', 'Bachelor of Science in Hospitality Management 3', 'sssssssssss', 0, 0, 'case_resolved', 'none', '', '2026-04-22 08:14:58', 'Medium', 'STUDENT ONLY', NULL),
(37, 'G-20260422-4D9340', 0, 12, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-22 08:15:41', NULL, NULL, '2026-04-22 08:17:13', 16, 'teacher', 'other', 'Financial Concern', 'Bachelor of Science in Tourism Management', 'ffffff', 0, 0, 'case_resolved', 'without_reformation', 'ddd', '2026-04-22 08:15:41', 'Medium', 'STUDENT ONLY', NULL),
(38, 'G-20260422-B628D0', 0, 12, NULL, 16, '', '', '', 'resolved', 'medium', '2026-04-22 11:12:33', NULL, NULL, '2026-04-22 11:33:13', 16, 'teacher', 'other', 'Peer Relationship', 'Bachelor of Science in Tourism Management', 'dddddddddd', 0, 0, 'case_resolved', 'none', '', '2026-04-22 11:12:33', 'High', 'STUDENT ONLY', NULL),
(39, 'G-20260422-187750', 0, 12, NULL, 16, '', '', '', 'closed', 'medium', '2026-04-22 11:53:07', NULL, NULL, '2026-05-16 10:35:30', 16, 'teacher', 'other', 'Financial Concern', 'Bachelor of Science in Tourism Management', 'ffffff', 0, 0, 'none', 'closed_case', 'Remarks: Admission of Fault\n\nNotes: kjbgb', '2026-04-22 11:53:07', 'Medium', 'STUDENT ONLY', 'Admission of Fault'),
(40, 'G-20260422-54ABAB', 0, 15, NULL, 16, '', '', '', 'closed', 'medium', '2026-04-22 11:59:21', NULL, NULL, '2026-05-14 10:26:11', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'aaaaaaaa', 0, 0, 'none', 'closed_case', 'Remark: Academic Stress | aaaasss', '2026-04-22 11:59:21', 'Medium', 'STUDENT ONLY', NULL),
(41, 'G-20260508-57A5F7', 0, 15, NULL, NULL, '', '', '', 'escalated', 'medium', '2026-05-08 07:23:46', NULL, NULL, '2026-05-28 12:07:19', 15, 'student', 'self_request', 'Family Matters', 'Bachelor of Science in Hospitality Management 3', 'di ako binibigyan ng baon', 0, 0, 'none', 'none', 'Outside Guidance Scope | Additional note: aa', '2026-05-08 07:23:46', 'Medium', 'STUDENT ONLY', NULL),
(42, 'G-20260516-297282', 0, 11, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-16 10:45:19', NULL, NULL, '2026-05-16 11:12:55', 16, 'teacher', 'other', 'Health Issue', 'Bachelor of Science in Computer Science Graduate', 'www', 0, 0, 'none', 'closed_case', 'Remarks: Mental Health Support\n\nNotes: aaaaaaa', '2026-05-16 10:45:19', 'Medium', 'STUDENT ONLY', 'Mental Health Support'),
(43, 'G-20260516-4FAB77', 0, 15, NULL, 16, '', '', '', 'in_progress', 'medium', '2026-05-16 13:11:01', NULL, NULL, '2026-05-16 13:11:01', 16, 'teacher', 'other', 'Peer Relationship', 'Bachelor of Science in Hospitality Management 3', 'hhhhhhh', 0, 0, 'none', 'none', 'gggggggg', '2026-05-16 13:11:01', 'Medium', 'STUDENT ONLY', NULL),
(44, 'G-20260516-134903', 0, NULL, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-16 13:45:46', NULL, NULL, '2026-07-16 07:51:24', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Business Administration / 2nd Year', 'sssssssss', 0, 0, 'none', 'closed_case', 'Remarks: Conflict Resolution\n\nNotes: wwwwwww', '2026-05-16 13:45:46', 'Medium', 'STUDENT ONLY', 'Conflict Resolution'),
(45, 'G-20260516-A7D0B0', 0, NULL, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-16 13:48:59', NULL, NULL, '2026-06-23 09:22:52', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Business Administration / 1st Year', 'ssss', 0, 0, 'none', 'closed_case', 'Remarks: Follow-up Scheduled\n\nNotes: sssssss', '2026-05-16 13:48:59', 'High', 'STUDENT ONLY', 'Follow-up Scheduled'),
(46, 'G-20260528-A90D88', 0, 36, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-28 12:10:38', NULL, NULL, '2026-05-28 12:29:26', 16, 'teacher', 'other', 'Peer Relationship', 'Bachelor of Science in Computer Science 3', 'bullying', 0, 0, 'none', 'closed_case', 'Remarks: Conflict Resolution\n\nNotes: well done', '2026-05-28 12:10:38', 'High', 'TEACHER AND STUDENT', 'Conflict Resolution'),
(47, 'G-20260528-E2A224', 0, 36, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-28 12:19:52', NULL, NULL, '2026-05-31 09:03:15', 36, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Computer Science 3', 'I am writing to formally request a counseling session to seek guidance regarding challenges I am currently facing in my peer relationships. Lately, I have been experiencing difficulties navigating certain social dynamics and interpersonal conflicts within my peer group. I believe that speaking with a counselor will help me gain better perspective, develop healthier communication skills, and manage the emotional impact of these relational issues.', 0, 0, 'none', 'closed_case', 'Remarks: Mental Health Support', '2026-05-28 12:19:52', 'Medium', 'STUDENT ONLY', 'Mental Health Support'),
(48, 'G-20260528-E1BEFA', 0, 36, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-28 12:35:30', NULL, NULL, '2026-05-31 09:04:03', 16, 'teacher', 'other', 'Peer Relationship', 'Bachelor of Science in Computer Science 3', 'Bullying', 0, 0, 'none', 'closed_case', 'Remarks: Mental Health Support', '2026-05-28 12:35:30', 'Emergency', 'STUDENT ONLY', 'Mental Health Support'),
(49, 'G-20260528-F84E4A', 0, NULL, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-28 13:06:26', NULL, NULL, '2026-05-31 09:02:55', 16, 'teacher', 'other', 'Family Matters', 'Bachelor of Science in Accountancy / 2nd Year', 'no allowance', 0, 0, 'none', 'closed_case', 'Remarks: Mental Health Support', '2026-05-28 13:06:26', 'Low', 'STUDENT ONLY', 'Mental Health Support'),
(50, 'G-20260528-3FC20E', 0, NULL, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-28 13:10:34', NULL, NULL, '2026-05-31 09:00:36', 16, 'teacher', 'other', 'Peer Relationship', 'Bachelor of Science in Computer Science / 1st Year', 'a', 0, 0, 'none', 'closed_case', 'Remarks: Academic Stress', '2026-05-28 13:10:34', 'Medium', 'TEACHER AND STUDENT', 'Academic Stress'),
(51, 'G-20260531-36579B', 0, 36, NULL, 16, '', '', '', 'closed', 'medium', '2026-05-31 09:04:31', NULL, NULL, '2026-05-31 09:13:19', 36, 'student', 'self_request', 'Other Concern', 'Bachelor of Science in Computer Science 3', 'aaa', 0, 0, 'none', 'closed_case', 'Remarks: Conflict Resolution\n\nNotes: Remarks: Family Problems', '2026-05-31 09:04:31', 'Medium', 'STUDENT ONLY', 'Conflict Resolution'),
(52, 'G-20260531-A7E013', 0, 36, NULL, 16, '', '', '', '', 'medium', '2026-05-31 09:07:04', NULL, NULL, '2026-06-23 09:48:43', 36, 'student', 'self_request', 'Family Matters', 'Bachelor of Science in Computer Science 3', 'SS', 0, 0, 'none', 'closed_case', 'Remarks: Mental Health Support\n\nNotes: zzz', '2026-05-31 09:07:04', 'Medium', 'TEACHER AND STUDENT', 'Mental Health Support'),
(53, 'G-20260531-B1D382', 0, 36, NULL, 16, '', '', '', '', 'medium', '2026-05-31 09:18:25', NULL, NULL, '2026-06-23 09:48:15', 36, 'student', 'self_request', 'Stress Management', 'Bachelor of Science in Computer Science 3', 'AAAA', 0, 0, 'none', 'closed_case', 'Remarks: Admission of Fault', '2026-05-31 09:18:25', 'Medium', 'STUDENT ONLY', 'Admission of Fault'),
(54, 'G-20260531-DA8A45', 0, 36, NULL, 16, '', '', '', '', 'medium', '2026-05-31 09:22:18', NULL, NULL, '2026-06-23 09:47:56', 36, 'student', 'self_request', 'Financial Concern', 'Bachelor of Science in Computer Science 3', 'A', 0, 0, 'none', 'closed_case', 'Remarks: Academic Stress\n\nNotes: aaa', '2026-05-31 09:22:18', 'Medium', 'STUDENT ONLY', 'Academic Stress'),
(55, 'G-20260604-179184', 0, 36, NULL, 16, '', '', '', 'in_progress', 'medium', '2026-06-04 14:18:07', NULL, NULL, '2026-09-03 12:04:16', 36, 'student', 'self_request', 'Financial Concern', 'Bachelor of Science in Computer Science 3', 'sss', 0, 0, 'none', 'none', NULL, '2026-06-04 14:18:07', 'Medium', 'TEACHER AND STUDENT', NULL),
(56, 'G-20260716-6ACE09', 0, 65, NULL, 16, '', '', '', 'closed', 'medium', '2026-07-16 07:47:56', NULL, NULL, '2026-07-16 07:51:57', 65, 'student', 'self_request', 'Family Matters', 'Bachelor of Science in Computer Science 4', 'ddsd', 0, 0, 'none', 'closed_case', 'Remarks: Follow-up Scheduled\n\nNotes: dddddd', '2026-07-16 07:47:56', 'Medium', 'STUDENT ONLY', 'Follow-up Scheduled'),
(57, 'G-20260903-77A722', 0, 36, NULL, 16, '', '', '', 'in_progress', 'medium', '2026-09-03 11:54:08', NULL, NULL, '2026-09-03 11:55:20', 36, 'student', 'self_request', 'Stress Management', 'Bachelor of Science in Computer Science 4', 'dont', 0, 0, 'none', 'none', NULL, '2026-09-03 11:54:08', 'Medium', 'STUDENT ONLY', NULL),
(58, 'G-20260903-4BFB3B', 0, 36, NULL, 16, '', '', '', 'in_progress', 'medium', '2026-09-03 12:04:49', NULL, NULL, '2026-09-03 12:05:10', 36, 'student', 'self_request', 'Family Matters', 'Bachelor of Science in Computer Science 4', 'asdas', 0, 0, 'none', 'none', NULL, '2026-09-03 12:04:49', 'Medium', 'TEACHER AND STUDENT', NULL),
(59, 'G-20260903-2E3419', 0, 36, NULL, 16, '', '', '', 'in_progress', 'medium', '2026-09-03 12:08:06', NULL, NULL, '2026-09-03 12:08:25', 36, 'student', 'self_request', 'Peer Relationship', 'Bachelor of Science in Computer Science 4', 'asas', 0, 0, 'none', 'none', NULL, '2026-09-03 12:08:06', 'Medium', 'Student and Parent/Guardian', NULL),
(60, 'G-20260903-8A4F49', 0, 36, NULL, 16, '', '', '', 'in_progress', 'medium', '2026-09-03 12:10:39', NULL, NULL, '2026-09-03 12:11:02', 36, 'student', 'self_request', 'Financial Concern', 'Bachelor of Science in Computer Science 4', 'asasd', 0, 0, 'none', 'none', NULL, '2026-09-03 12:10:39', 'Medium', 'Student and Parent/Guardian', NULL),
(61, 'G-20260904-10477D', 0, 36, NULL, NULL, '', '', '', 'open', 'medium', '2026-09-04 10:52:16', NULL, NULL, '2026-09-04 10:52:16', 36, 'student', 'self_request', 'Stress Management', 'Bachelor of Science in Computer Science 4', 'diko alam', 0, 0, 'none', 'none', NULL, '2026-09-04 10:52:16', 'Medium', 'STUDENT ONLY', NULL),
(62, 'G-20260905-BAB823', 0, 69, NULL, NULL, '', '', '', 'open', 'medium', '2026-09-05 09:42:32', NULL, NULL, '2026-09-05 09:42:32', 69, 'student', 'self_request', 'Financial Concern', 'Bachelor of Science in Accountancy 1', 'PAHINGE PONG PERA. SALAMAT', 0, 0, 'none', 'none', NULL, '2026-09-05 09:42:32', 'Medium', 'STUDENT ONLY', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `guidance_case_attachments`
--

CREATE TABLE `guidance_case_attachments` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guidance_case_attachments`
--

INSERT INTO `guidance_case_attachments` (`id`, `case_id`, `filename`, `filepath`, `uploaded_by`, `uploaded_at`) VALUES
(11, 12, 'Screenshot 2025-10-29 133516.png', 'uploads/guidance/doc_69e3075d1da2d_Screenshot 2025-10-29 133516.png', 15, '2026-04-18 12:23:57'),
(12, 13, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e3094aba269_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:32:10'),
(13, 14, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30975f0447_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:32:53'),
(14, 15, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30995c8243_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:33:25'),
(15, 16, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30ce6a65a8_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:47:34'),
(16, 17, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30d5628a9f_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:49:26'),
(17, 18, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30d741a59a_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:49:56'),
(18, 19, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30e6537d5c_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:53:57'),
(19, 20, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30e7027ac2_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:54:08'),
(20, 21, 'Screenshot 2025-10-23 165840.png', 'uploads/guidance/doc_69e30f6b52175_Screenshot 2025-10-23 165840.png', 15, '2026-04-18 12:58:19'),
(21, 22, 'Screenshot 2025-10-30 143148.png', 'uploads/guidance/manual_69e48b4f9145c_Screenshot 2025-10-30 143148.png', 16, '2026-04-19 15:59:11'),
(22, 23, 'Screenshot 2025-10-24 223149.png', 'uploads/guidance/manual_69e48bbdd8647_Screenshot 2025-10-24 223149.png', 16, '2026-04-19 16:01:01'),
(23, 24, 'Screenshot 2025-10-24 223149.png', 'uploads/guidance/manual_69e48d18a594b_Screenshot 2025-10-24 223149.png', 16, '2026-04-19 16:06:48'),
(24, 25, 'Screenshot 2025-10-24 223149.png', 'uploads/guidance/manual_69e490a1d1ea4_Screenshot 2025-10-24 223149.png', 16, '2026-04-19 16:21:53'),
(25, 26, 'Screenshot 2025-10-24 223149.png', 'uploads/guidance/manual_69e490d76a372_Screenshot 2025-10-24 223149.png', 16, '2026-04-19 16:22:47'),
(26, 27, 'Screenshot 2025-10-24 223149.png', 'uploads/guidance/manual_69e4913906037_Screenshot 2025-10-24 223149.png', 16, '2026-04-19 16:24:25'),
(27, 28, 'Screenshot 2025-10-24 223149.png', 'uploads/guidance/manual_69e493ef0e841_Screenshot 2025-10-24 223149.png', 16, '2026-04-19 16:35:59'),
(28, 29, 'Screenshot 2025-10-30 143139.png', 'uploads/guidance/manual_69e4995f8944d_Screenshot 2025-10-30 143139.png', 16, '2026-04-19 16:59:11'),
(29, 29, 'Screenshot 2025-10-24 223149.png', '/uploads/case_documents/case_29_69e59e9da3306.png', 16, '2026-04-20 11:33:49'),
(30, 29, 'Screenshot 2025-10-24 223149.png', '/uploads/case_documents/case_29_69e59ea8b1b62.png', 16, '2026-04-20 11:34:00'),
(31, 29, 'Screenshot 2025-10-23 165840.png', '/uploads/case_documents/case_29_69e59faeb4503.png', 16, '2026-04-20 11:38:22'),
(32, 30, 'Screenshot 2025-10-29 133516.png', 'uploads/guidance/manual_69e5ada9697bf_Screenshot 2025-10-29 133516.png', 16, '2026-04-20 12:38:01'),
(33, 41, 'Fern.docx', 'uploads/guidance/letter_69fd1f02505c4_Fern.docx', 15, '2026-05-08 07:23:46'),
(34, 42, 'Gemini_Generated_Image_xdj23qxdj23qxdj2 (1).png', 'uploads/guidance/manual_6a07da3fec25b_Gemini_Generated_Image_xdj23qxdj23qxdj2 (1).png', 16, '2026-05-16 10:45:19'),
(35, 47, 'Fern.docx', 'uploads/guidance/letter_6a17c268e28cf_Fern.docx', 36, '2026-05-28 12:19:52'),
(36, 51, 'Screenshot 2025-10-28 193520.png', 'uploads/guidance/letter_6a1b891f8de19_Screenshot 2025-10-28 193520.png', 36, '2026-05-31 09:04:31'),
(37, 52, 'Screenshot 2025-10-28 193520.png', 'uploads/guidance/letter_6a1b89b835a70_Screenshot 2025-10-28 193520.png', 36, '2026-05-31 09:07:04'),
(38, 53, 'Screenshot 2025-10-28 200414.png', 'uploads/guidance/letter_6a1b8c61458de_Screenshot 2025-10-28 200414.png', 36, '2026-05-31 09:18:25'),
(39, 54, 'Screenshot 2025-10-30 143139.png', 'uploads/guidance/letter_6a1b8d4a4c2f8_Screenshot 2025-10-30 143139.png', 36, '2026-05-31 09:22:18'),
(40, 55, 'clinic_needs_report.doc', 'uploads/guidance/letter_6a21189f1637e_clinic_needs_report.doc', 36, '2026-06-04 14:18:07'),
(41, 52, 'clinic_needs_report.doc', 'uploads/guidance/case_6a2619045450c_clinic_needs_report.doc', 16, '2026-06-08 09:21:08'),
(42, 53, 'LETTERHEAD (1).docx', 'uploads/case_attachments/case_53_6a39dc8f473ec_LETTERHEAD (1).docx', 16, '2026-06-23 09:08:31'),
(43, 45, 'LETTERHEAD (1).docx', 'uploads/case_attachments/case_45_6a39dce02acfe_LETTERHEAD (1).docx', 16, '2026-06-23 09:09:52'),
(44, 45, 'LETTERHEAD (1).docx', 'uploads/case_attachments/case_45_6a39dfec4dd4f_LETTERHEAD (1).docx', 16, '2026-06-23 09:22:52'),
(45, 56, 'library_popular_reserved_report (6).docx', 'uploads/guidance/letter_6a581c2c1ffe3_library_popular_reserved_report (6).docx', 65, '2026-07-16 07:47:56'),
(46, 56, 'letter head ssc.docx', 'uploads/case_attachments/case_56_6a581d1d73e7f_letter head ssc.docx', 16, '2026-07-16 07:51:57'),
(47, 57, 'requirements.txt', 'uploads/guidance/letter_6a98ef6049df4_requirements.txt', 36, '2026-09-03 11:54:08'),
(48, 58, 'requirements.txt', 'uploads/guidance/letter_6a98f1e19ff60_requirements.txt', 36, '2026-09-03 12:04:49'),
(49, 59, 'requirements.txt', 'uploads/guidance/letter_6a98f2a6e83aa_requirements.txt', 36, '2026-09-03 12:08:06'),
(50, 60, 'requirements.txt', 'uploads/guidance/letter_6a98f33fce0f9_requirements.txt', 36, '2026-09-03 12:10:39'),
(51, 61, 'Picture1.png', 'uploads/guidance/letter_6a9a3260533c4_Picture1.png', 36, '2026-09-04 10:52:16'),
(52, 62, 'jolli.jpg', 'uploads/guidance/letter_6a9b73882aa82_jolli.jpg', 69, '2026-09-05 09:42:32');

-- --------------------------------------------------------

--
-- Table structure for table `guidance_case_feedback`
--

CREATE TABLE `guidance_case_feedback` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `service_rating` tinyint(4) DEFAULT 0,
  `feedback_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guidance_case_feedback`
--

INSERT INTO `guidance_case_feedback` (`id`, `case_id`, `student_id`, `service_rating`, `feedback_text`, `created_at`, `updated_at`) VALUES
(1, 51, 36, 4, 'DFDF', '2026-06-08 00:16:13', '2026-06-08 00:16:13');

-- --------------------------------------------------------

--
-- Table structure for table `guidance_observations`
--

CREATE TABLE `guidance_observations` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `observer_id` int(11) NOT NULL,
  `observer_role` enum('teacher','student','counselor','admin') NOT NULL DEFAULT 'teacher',
  `observation` text NOT NULL,
  `observed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guidance_sessions`
--

CREATE TABLE `guidance_sessions` (
  `id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `session_time` time DEFAULT NULL,
  `summary` text NOT NULL,
  `next_steps` text DEFAULT NULL,
  `counselor_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `session_category` enum('under_guidance_counseling','parent_guardian_conference','teacher_awareness','professional_evaluation','follow_up_session','behavioral_monitoring','conflict_mediation','academic_support_referral') NOT NULL DEFAULT 'under_guidance_counseling',
  `participation_scope` enum('student_only','with_parent_guardian','with_teacher_awareness') NOT NULL DEFAULT 'student_only',
  `attendance_status` enum('attended','non_appearance','pending') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guidance_sessions`
--

INSERT INTO `guidance_sessions` (`id`, `case_id`, `session_date`, `session_time`, `summary`, `next_steps`, `counselor_id`, `created_at`, `session_category`, `participation_scope`, `attendance_status`, `notes`) VALUES
(3, 5, '2026-04-19', NULL, '', NULL, 16, '2026-04-18 12:24:12', 'under_guidance_counseling', 'student_only', 'pending', 'yeayeah'),
(4, 5, '2026-04-19', NULL, '', NULL, 16, '2026-04-18 12:28:06', 'under_guidance_counseling', 'student_only', 'pending', 'yeayeah'),
(5, 5, '2026-04-19', NULL, '', NULL, 16, '2026-04-18 12:31:37', 'under_guidance_counseling', 'student_only', 'pending', 'yeayeah'),
(6, 5, '2026-04-19', NULL, '', NULL, 16, '2026-04-18 12:32:18', 'under_guidance_counseling', 'student_only', 'pending', 'yeayeah'),
(7, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 14:09:13', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(8, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 15:03:57', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(9, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 15:28:58', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(10, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 15:30:36', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(11, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 15:34:16', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(12, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 15:34:22', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(13, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 15:34:28', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(14, 16, '2026-04-20', NULL, '', NULL, 16, '2026-04-18 15:41:24', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. amlsmdlandn  oasd Mode: Face-to-Face. Location/Link: white room. Participants: Student and Parent/Guardian'),
(15, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 14:48:12', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(16, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 14:58:33', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(17, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:01:23', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(18, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:07:22', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(19, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:10:51', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(20, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:11:01', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(21, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:17:43', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(22, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:18:22', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(23, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:22:30', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(24, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:28:42', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(25, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:33:11', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(26, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:35:21', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(27, 16, '2026-04-19', NULL, '', NULL, 16, '2026-04-19 15:42:06', 'follow_up_session', 'student_only', 'non_appearance', 'a'),
(28, 22, '2026-04-20', NULL, '', NULL, 16, '2026-04-19 15:59:11', 'under_guidance_counseling', 'student_only', 'pending', 'Initial session scheduled upon case creation. Mode: Face-to-Face. Venue: TTTTTTTT'),
(29, 23, '2026-04-20', NULL, '', NULL, 16, '2026-04-19 16:01:01', 'under_guidance_counseling', 'student_only', 'pending', 'Initial session scheduled upon case creation. Mode: Face-to-Face. Venue: AAAAAAAAAAAA'),
(30, 24, '2026-04-20', NULL, '', NULL, 16, '2026-04-19 16:06:48', 'under_guidance_counseling', 'student_only', 'pending', 'Initial session scheduled upon case creation. Mode: Face-to-Face. Venue: AAAAAAAAAAAA'),
(31, 25, '2026-04-20', NULL, '', NULL, 16, '2026-04-19 16:21:53', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue: AAAAAAAAAAAA. Participants: student_only'),
(32, 26, '2026-04-20', NULL, '', NULL, 16, '2026-04-19 16:22:47', 'under_guidance_counseling', 'student_only', 'pending', 'Initial session scheduled upon case creation. Mode: Face-to-Face. Venue: AAAAAAAAAAAA'),
(33, 27, '2026-04-20', NULL, '', NULL, 16, '2026-04-19 16:24:25', 'under_guidance_counseling', 'student_only', 'pending', 'Initial session scheduled upon case creation. Mode: Face-to-Face. Venue: AAAAAAAAAAAA'),
(34, 28, '2026-04-20', NULL, '', NULL, 16, '2026-04-19 16:35:59', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue: AAAAAAAAAAAA'),
(35, 31, '2026-04-07', NULL, '', NULL, 16, '2026-04-20 13:16:31', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue: WHITE ROOM'),
(36, 32, '2026-04-21', NULL, '', NULL, 16, '2026-04-20 13:52:56', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue: AAAAAA'),
(37, 33, '2026-04-21', NULL, '', NULL, 16, '2026-04-20 14:13:24', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue: dfdddddfs'),
(38, 34, '2026-04-23', '13:00:00', '', NULL, 16, '2026-04-20 14:43:04', 'under_guidance_counseling', 'student_only', 'attended', 'Mode: Face-to-Face'),
(39, 35, '2026-04-24', '09:56:00', '', NULL, 16, '2026-04-21 09:56:07', 'follow_up_session', 'student_only', 'pending', 'Mode: Face-to-Face. Venue: dfdddddfs'),
(40, 36, '2026-04-24', '08:00:00', '', NULL, 16, '2026-04-22 08:14:58', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue/Location: sssssssss'),
(41, 37, '2026-04-25', '08:00:00', '', NULL, 16, '2026-04-22 08:15:41', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Online. Meeting Link/Platform: dsssss'),
(42, 38, '2026-04-29', '08:00:00', '', NULL, 16, '2026-04-22 11:12:33', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue/Location: cccccccccc'),
(43, 39, '2026-04-27', '09:00:00', '', NULL, 16, '2026-04-22 11:53:07', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue/Location: fffffff'),
(44, 40, '2026-04-23', '08:00:00', '', NULL, 16, '2026-04-22 11:59:21', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Face-to-Face. Venue/Location: aaaaaaaa'),
(45, 46, '2026-06-05', '08:00:00', '', NULL, 16, '2026-05-28 12:10:38', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Online'),
(46, 48, '2026-06-05', '08:00:00', '', NULL, 16, '2026-05-28 12:35:30', 'under_guidance_counseling', 'student_only', 'pending', 'Mode: Online'),
(47, 47, '2026-06-23', '10:00:00', '', NULL, 16, '2026-05-31 09:01:31', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. Mode: Face-to-Face. Venue/Location: guidance office. Participants: Student and Parent/Guardian'),
(48, 51, '2026-06-17', '11:04:00', '', NULL, 16, '2026-05-31 09:04:55', 'under_guidance_counseling', '', 'pending', 'Mode: Face-to-Face. Venue: SS'),
(49, 52, '2026-06-15', '09:13:00', '', NULL, 16, '2026-05-31 09:15:32', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. II Mode: Face-to-Face. Venue/Location: III. Participants: Student and Parent/Guardian'),
(50, 53, '2026-06-25', '08:18:00', '', NULL, 16, '2026-05-31 09:19:05', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. A Mode: Face-to-Face. Venue/Location: AA. Participants: Student and Parent/Guardian'),
(51, 54, '2026-06-26', '12:22:00', '', NULL, 16, '2026-05-31 09:22:48', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. Mode: Face-to-Face. Venue/Location: D. Participants: Student and Parent/Guardian'),
(52, 56, '2026-07-29', '07:52:00', '', NULL, 16, '2026-07-16 07:50:16', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. meronb Mode: Face-to-Face. Location/Link: pending. Participants: Student only'),
(53, 57, '2026-12-31', '12:54:00', '', NULL, 16, '2026-09-03 11:55:20', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. dika nag bayad tuition Mode: Face-to-Face. Venue/Location: sa office. Participants: Student and Parent/Guardian'),
(54, 55, '2026-09-03', '12:04:00', '', NULL, 16, '2026-09-03 12:04:16', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. s Mode: Online. Meeting Link/Platform: asasasas. Participants: Student and Teacher'),
(55, 58, '2026-09-03', '12:05:00', '', NULL, 16, '2026-09-03 12:05:10', 'under_guidance_counseling', '', 'pending', 'Mode: Online. Link: sasasdasd'),
(56, 59, '2026-09-03', '12:08:00', '', NULL, 16, '2026-09-03 12:08:25', 'under_guidance_counseling', '', 'pending', 'First session scheduled upon case acceptance. Mode: Online. Meeting Link/Platform: swasaddsadasdas. Participants: Student and Parent/Guardian'),
(57, 60, '2026-09-03', '12:11:00', '', NULL, 16, '2026-09-03 12:11:02', 'under_guidance_counseling', '', 'pending', 'Mode: Face-to-Face. Venue: sasas');

-- --------------------------------------------------------

--
-- Table structure for table `library_books`
--

CREATE TABLE `library_books` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(150) NOT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `lcc_letter_line` varchar(10) DEFAULT NULL,
  `lcc_class_number` varchar(50) DEFAULT NULL,
  `lcc_cutter_code` varchar(50) DEFAULT NULL,
  `lcc_published_year` varchar(10) DEFAULT NULL,
  `lcc_additional_info` varchar(100) DEFAULT NULL,
  `additional_info_type` enum('volume','part','number','copies') DEFAULT NULL,
  `additional_info_quantity` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('Available','Borrowed','Reserved','Under Repair') NOT NULL DEFAULT 'Available',
  `total_copies` int(11) NOT NULL DEFAULT 1,
  `available_copies` int(11) NOT NULL DEFAULT 1,
  `rating` decimal(2,1) DEFAULT 0.0,
  `overview` text DEFAULT NULL,
  `cover_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_books`
--

INSERT INTO `library_books` (`id`, `title`, `author`, `isbn`, `lcc_letter_line`, `lcc_class_number`, `lcc_cutter_code`, `lcc_published_year`, `lcc_additional_info`, `additional_info_type`, `additional_info_quantity`, `category`, `status`, `total_copies`, `available_copies`, `rating`, `overview`, `cover_url`, `created_at`) VALUES
(7, 'To Kill a Mockingbird', 'Harper Lee', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BSCS', 'Available', 10, 10, 4.0, 'To Kill a Mockingbird is a 1960 Southern Gothic novel by American author Harper Lee. It became instantly successful after its release; in the United States, it is widely read in high schools and middle schools.[1] To Kill a Mockingbird won the Pulitzer Prize a year after its release, and it has become a classic of modern American literature.[2] The plot and characters are loosely based on Lee\'s observations of her family, her neighbors and an event that occurred near her hometown of Monroeville, Alabama, in 1936, when she was ten.[3]\r\n\r\nDespite dealing with the serious issues of rape and racial inequality, the novel is renowned for its warmth and humor. Atticus Finch, the narrator\'s father, has served as a moral hero for many readers and as a model of integrity for lawyers. The historian Joseph Crespino explains, \"In the twentieth century, To Kill a Mockingbird is probably the most widely read book dealing with race in America, and its main character, Atticus Finch, the most enduring fictional image of racial heroism.\"[4] As a Southern Gothic novel and Bildungsroman, the primary themes of To Kill a Mockingbird involve racial injustice and the destruction of innocence. Scholars have noted that Lee also addresses issues of class, courage, compassion, and gender roles in the Deep South. Lessons from the book emphasize tolerance and decry prejudice.[5] Despite its themes, To Kill a Mockingbird has been subject to campaigns for removal from public classrooms, often challenged for its use of racial epithets. In 2006, British librarians ranked the book ahead of the Bible as one \"every adult should read before they die\".[6]\r\n\r\nReaction to the novel varied widely upon publication. Despite the number of copies sold and its widespread use in education, literary analysis of it is sparse. Author Mary McDonough Murphy, who collected individual impressions of To Kill a Mockingbird by several authors and public figures, calls the book \"an astonishing phenomenon\".[7] It was adapted into the 1962 Oscar-winning film of the same name by director Robert Mulligan, with a screenplay by Horton Foote. Since 1990, a play based on the novel has been performed annually in Harper Lee\'s hometown.\r\n\r\nTo Kill a Mockingbird was Lee\'s only published book until Go Set a Watchman, an earlier draft of To Kill a Mockingbird, was published on July 14, 2015. Lee continued to respond to her work\'s impact until her death in February 2016. She was very guarded about her personal life, and gave her last interview to a journalist in 1964.[8]', 'IMG ASSETS/library_covers/cover_69ed71dfc751f6.23865110.jpg', '2026-04-26 10:01:03'),
(8, 'Pride and Prejudice', 'Jane Austen', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BSBA', 'Borrowed', 1, 0, 0.0, 'Pride and Prejudice is the second published novel (but third to be written) by English author Jane Austen, written when she was aged 20–21, and later published in 1813.\r\n\r\nA novel of manners, it follows the character development of Elizabeth Bennet, the protagonist of the book, who learns about the repercussions of hasty judgments and comes to appreciate the difference between superficial goodness and actual goodness.\r\n\r\nHer father Mr Bennet, owner of the Longbourn estate in Hertfordshire, has five daughters; but this estate is entailed by a strict settlement[1] that Mr Bennet entered into when coming of age, so now can only be inherited in the male line. His wife brought a settlement[2] of £5,000 into the marriage as her \'separate estate\', and has since inherited an additional £4,000 on the death of her father; but Mrs Bennet and her daughters face living only on the interest from these sums upon Mr Bennet\'s death. To his regret, he has failed to save out of the income from the Longbourn estate to provide enhanced marriage portions for his daughters. From the Bennets\' perspective, it is imperative that at least one of their daughters marry well to support the others, which is a primary motivation driving the plot.\r\n\r\nPride and Prejudice has consistently appeared near the top of lists of \"most-loved books\" among literary scholars and the reading public. It has become one of the most popular novels in English literature, with over 20 million copies sold, and has inspired many derivatives in modern literature.[3][4] For more than a century, dramatic adaptations, reprints, unofficial sequels, films, and TV versions of Pride and Prejudice have portrayed the memorable characters and themes of the novel, reaching mass audiences.[5]', 'IMG ASSETS/library_covers/cover_69ed726c14ad28.17764304.jpg', '2026-04-26 10:03:24'),
(9, 'On the Origin of Species', 'Charles Darwin', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BSCRIM', 'Available', 1, 1, 0.0, 'On the Origin of Species by Means of Natural Selection, or the Preservation of Favoured Races in the Struggle for Life[3] is a work of scientific literature by Charles Darwin that is considered to be the foundation of evolutionary biology. It was published on 24 November 1859.[4] Darwin\'s book introduced the scientific theory that populations evolve over the course of generations through a process of natural selection, although Lamarckism was also included as a mechanism of lesser importance. The book presented a body of evidence that the diversity of life arose by common descent through a branching pattern of evolution. Darwin included evidence that he had collected on the Beagle expedition in the 1830s and his subsequent findings from research, correspondence, and experimentation.[5]\r\n\r\nVarious evolutionary ideas had already been proposed to explain new findings in biology. There was growing support for such ideas among dissident anatomists and the general public, but during the first half of the 19th century the English scientific establishment was closely tied to the Church of England, while science was part of natural theology. Ideas about the transmutation of species were controversial as they conflicted with the beliefs that species were unchanging parts of a designed hierarchy and that humans were unique, unrelated to other animals. The political and theological implications were intensely debated, but transmutation was not accepted by the scientific mainstream.\r\n\r\nThe book was written for non-specialist readers and attracted widespread interest upon its publication. Darwin was already highly regarded as a scientist, so his findings were taken seriously and the evidence he presented generated scientific, philosophical, and religious discussion. The debate over the book contributed to the campaign by T. H. Huxley and his fellow members of the X Club to secularise science by promoting scientific naturalism. Within two decades, there was widespread scientific agreement that evolution, with a branching pattern of common descent, had occurred, but scientists were slow to give natural selection the significance that Darwin thought appropriate. During \"the eclipse of Darwinism\" from the 1880s to the 1930s, various other mechanisms of evolution were given more credit. With the development of the modern evolutionary synthesis in the 1930s and 1940s, Darwin\'s concept of evolutionary adaptation through natural selection became central to modern evolutionary theory, and it has now become the unifying concept of the life sciences.', 'IMG ASSETS/library_covers/cover_69ed73278fbb91.27941848.jpg', '2026-04-26 10:06:31'),
(10, 'The Fish That Ate the Whale: The Life and Times of America\'s Banana King', 'Rich Cohen', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '12346', 'Borrowed', 1, 0, 0.0, 'amed a Best Book of the Year by the San Francisco Chronicle and The Times-Picayune\r\n\r\nThe fascinating untold tale of Samuel Zemurray, the self-made banana mogul who went from penniless roadside banana peddler to kingmaker and capitalist revolutionary\r\n\r\nWhen Samuel Zemurray arrived in America in 1891, he was tall, gangly, and penniless. When he died in the grandest house in New Orleans sixty-nine years later, he was among the richest, most powerful men in the world. Working his way up from a roadside fruit peddler to conquering the United Fruit Company, Zemurray became a symbol of the best and worst of the United States: proof that America is the land of opportunity, but also a classic example of the corporate pirate who treats foreign nations as the backdrop for his adventures.\r\n\r\nZemurray lived one of the great untold stories of the last hundred years. Starting with nothing but a cart of freckled bananas, he built a sprawling empire of banana cowboys, mercenary soldiers, Honduran peasants, CIA agents, and American statesmen. From hustling on the docks of New Orleans to overthrowing Central American governments and precipitating the bloody thirty-six-year Guatemalan civil war, the Banana Man lived a monumental and sometimes dastardly life. Rich Cohen\'s brilliant historical profile The Fish That Ate the Whale unveils Zemurray as a hidden power broker, driven by an indomitable will to succeed.', '', '2026-04-26 10:11:05'),
(33, 'Handbook on First Aid, Rescue &Water Safety', 'Jezrel B. Vicente, Rhescien Mae B. Vicente, Maria Krisia Fae R. De Asis', '', 'Fil Crim', '363.2', 'V632', '2015', NULL, 'copies', 4, 'Criminology', 'Available', 4, 4, 0.0, 'The Handbook on First Aid, Rescue & Water Safety by Jezrel B. Vicente, Rhescien Mae B. Vicente, and Maria Krisia Fae R. De Asis is a comprehensive and practical instructional manual designed primarily for Criminology students, law enforcement trainees, and emergency responders in the Philippines. Spanning seven chapters, the book provides direct, step-by-step guidance on core life-saving principles, beginning with immediate First Aid response, vital signs assessment, wound bandaging, and Cardiopulmonary Resuscitation (CPR) protocols. Beyond land-based emergencies, it emphasizes water safety and survival, equipping readers with critical techniques to prevent drowning, manage aquatic hazards, and execute secure emergency rescue transfers without aggravating a victim\'s injuries. Furthermore, the handbook extends into wilderness survival or fieldcraft—teaching essential skills like psychological endurance, foraging for clean water, and identifying local medicinal plants—alongside basic ropemanship for securing equipment and executing tactical vertical rescues. Ultimately, this manual serves as an essential, highly functional reference aimed at preserving life, preventing further injury, and promoting immediate recovery during critical accidents and mass-casualty disasters.', 'IMG ASSETS/library_covers/cover_6a0fb7d8ba0f36.57695354.jpg', '2026-05-22 09:56:40'),
(34, 'Criminological Research and Statistics', 'CSUPT. Mercedes A. Foronda (Ret) DPA, Ph.D.', '', 'Fil Crim.', '364.7', 'F727', '2012', NULL, 'copies', 2, 'General', 'Available', 2, 2, 0.0, 'The Criminological Research and Statistics by CSUPT. Mercedes A. Foronda (Ret), DPA, Ph.D. is a comprehensive and structured instructional manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Divided into two major components, the book provides direct, step-by-step guidance on core scientific principles, beginning with the foundations of criminological inquiry, research problem formulation, and ethical thesis writing. Beyond basic academic concepts, it emphasizes practical methodology and quantitative analysis, equipping readers with critical techniques to utilize various research designs—such as descriptive, correlational, and experimental approaches—to study crime patterns systematically. Furthermore, the handbook extends into statistical applications—teaching essential skills like data categorization through levels of measurement, computation of descriptive statistics, and the interpretation of inferential tools—alongside practical data processing necessary for objective criminal justice policy-making. Ultimately, this manual serves as an essential, highly functional reference aimed at untangling complex data concepts, equipping future criminologists with research capabilities, and promoting empirical, data-driven solutions within the Philippine justice system.', 'IMG ASSETS/library_covers/cover_6a0fb95d64fc27.40358720.jpg', '2026-05-22 10:01:36'),
(36, 'Police Ethics and Police Community Relations', 'Adelene Maghinay Florendo', '', 'Fil Crim.', '363.23', 'F632', '2012', NULL, 'copies', 1, 'Criminology', 'Available', 1, 1, 0.0, 'The Police Ethics and Police Community Relations by PCol. Adelene Maghinay Florendo, PNP (Ret.) is a comprehensive and structured instructional manual designed primarily for Criminology students, law enforcement trainees, and police personnel in the Philippines. Divided into two fundamental components, the book provides direct, step-by-step guidance on core professional principles, beginning with the foundations of moral conduct, the PNP Code of Professional Conduct and Ethical Standards, and the provisions of Republic Act 6713. Beyond theoretical ethics, it emphasizes practical law enforcement application, equipping readers with critical techniques to build institutional transparency, counter corruption, and manage ethical dilemmas during standard operations. Furthermore, the handbook extends into public relations strategies—teaching essential skills like community-oriented policing, the mechanics of the Police Community Relations (PCR) master plan, and methods for fostering multi-sectoral partnerships—alongside collaborative communication necessary for restoring public trust and reducing local crime rates. Ultimately, this manual serves as an essential, highly functional reference aimed at bridging the gap between the police force and the community, equipping future public safety officers with deep moral accountability, and promoting a unified, citizen-centric approach within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fb9e3964933.85846667.jpg', '2026-05-22 10:05:23'),
(37, 'Fundamentals of Criminological Research & Statistics', 'Carlito D. Garcia, Ed.D', '', 'Fil Crim.', '364.7', 'G216', '2015', NULL, 'copies', 2, 'Criminology', 'Available', 2, 2, 0.0, 'The Fundamentals of Criminological Research & Statistics by Carlito D. Garcia, Ed.D. is a comprehensive and structured instructional manual designed primarily for Criminology students, law enforcement trainees, and thesis writers in the Philippines. Spanning fifteen chapters, the book provides direct, step-by-step guidance on core academic principles, beginning with the nature of scientific inquiry, literature mechanics, and the systematic formulation of a manageable research problem. Beyond basic theoretical frameworks, it emphasizes practical methodology and design execution, equipping readers with critical techniques to choose between qualitative approaches and standard quantitative methodologies, select research participants, and construct the first two chapters of a formal thesis proposal. Furthermore, the handbook extends into statistical data processing—teaching essential skills like planning data interpretation, presenting findings using descriptive statistics, and applying correlation and tests of significant difference—alongside structural formatting necessary for final research reporting. Ultimately, this manual serves as an essential, highly functional reference aimed at presenting research and thesis concepts in the simplest, most practical way, equipping future criminologists with necessary technical competencies, and promoting scientific, evidence-based studies within the criminal justice program.', 'IMG ASSETS/library_covers/cover_6a0fba5ab5cd02.09037066.jpg', '2026-05-22 10:07:22'),
(38, 'Polygraphy Lie Detection', 'Prof. Joner C. Villaluz-Rcrim.Ph.D, Col. William A. Revisa-(Ret)Ph.D., Carlito Panganoron , Jr. - Ph.D.', '', 'Fil Crim.', '362.254', 'V714', '2014', NULL, 'copies', 6, 'Criminology', 'Available', 6, 6, 0.0, 'The Polygraphy: Lie Detection by Prof. Joner C. Villaluz, RCrim, Ph.D., Col. William A. Revisa (Ret), Ph.D., and Carlito Panganoron, Jr., Ph.D. is a comprehensive and structured instructional manual designed primarily for Criminology students, law enforcement trainees, and forensic science practitioners in the Philippines. Divided into key foundational components, the book provides direct, step-by-step guidance on core scientific principles, beginning with the historical evolution of deception detection, from ancient, superstitious trials by ordeal to modern psychophysiological evaluation. Beyond historical context, it emphasizes practical machinery and physiological mechanics, equipping readers with critical techniques to understand the tripod foundation of polygraphy—specifically how the nervous system triggers automatic, recordable responses in respiration, blood pressure, and skin conductivity during interrogation. Furthermore, the handbook extends into examination protocols—teaching essential skills like subject pre-test preparation, strategic question formulation, and the visual chart interpretation of polygraph graphs—alongside the legal parameters governing custodial investigation and evidence admissibility in Philippine courts. Ultimately, this manual serves as an essential, highly functional reference aimed at demystifying the art and science of unmasking deception, equipping future criminologists with technical forensic capabilities, and promoting standardized, scientific truth-verification within the criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fbafaab6e14.22499517.jpg', '2026-05-22 10:10:02'),
(39, 'Juvenile Deliquency and Crime Prevention', 'Jesster P. Eduardo, Carlito Panganoron', '', 'Fil Crim.', '364.4', 'Ed24', '2016', NULL, 'copies', 4, 'Criminology', 'Available', 4, 4, 0.0, 'The Juvenile Delinquency and Crime Prevention by Jesster P. Eduardo and Carlito Panganoron, Jr. is a comprehensive and structured instructional manual designed primarily for Criminology students, law enforcement trainees, and juvenile justice advocates in the Philippines. Spanning key legislative and behavioral frameworks, the book provides direct, step-by-step guidance on core sociological principles, beginning with the theories of delinquency, the psychological and environmental factors influencing youth offenses, and the legal definition of a Child in Conflict with the Law (CICL). Beyond academic definitions, it emphasizes practical statutory application, equipping readers with a critical understanding of the Juvenile Justice and Welfare Act (Republic Act 9344, as amended by RA 10630), specifically highlighting the roles of the Barangay, local social workers, and law enforcement during initial contact and diversion proceedings. Furthermore, the handbook extends into proactive intervention strategies—teaching essential skills like formulating community-based crime prevention programs, managing rehabilitation frameworks, and implementing restorative justice practices—alongside family and school collaborative interventions necessary for keeping vulnerable youth away from the formal justice system. Ultimately, this manual serves as an essential, highly functional reference aimed at shifting the perspective from punitive punishment to rehabilitation, equipping future criminologists with empathetic enforcement capabilities, and promoting protective, citizen-centric crime prevention measures within the Philippine justice system.', 'IMG ASSETS/library_covers/cover_6a0fbb8d208421.07088291.jpg', '2026-05-22 10:12:29'),
(40, 'Criminology Glossary', 'Ricardo M. Guevara', '', 'Ref Crim.', '364.3', 'G939', '2014', NULL, 'copies', 1, 'Criminology', 'Available', 1, 1, 0.0, 'The Criminology Glossary by Ricardo M. Guevara is a comprehensive and structured reference manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Alphabetically arranged, the book provides direct, step-by-step guidance on core technical terminology, beginning with foundational definitions in criminalistics, law enforcement administration, and criminal law and jurisprudence. Beyond a standard dictionary layout, it emphasizes contextual academic application, equipping readers with critical techniques to distinguish between closely related legal terms, technical jargon, and specialized jargon used in correctional administration and sociology of crimes. Furthermore, the handbook extends into cross-disciplinary concepts—teaching essential vocabulary across scientific crime detection, operational police procedures, and court-admissible evidence frameworks—alongside practical study aids necessary for rapid recall during board exam preparations. Ultimately, this manual serves as an essential, highly functional reference aimed at untangling dense legal and technical language, equipping future criminologists with necessary linguistic precision, and promoting standardized, professional communication within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fbc14500e30.34104404.jpg', '2026-05-22 10:14:44'),
(41, 'Criminalistics Criminal Justice Education Comprehensive Digest', 'Ricardo M. Guevara', '', 'Fil Crim.', '364.7', 'Am484', '2014', NULL, 'copies', 1, 'Criminology', 'Available', 1, 1, 0.0, 'The Criminology Glossary by Ricardo M. Guevara is a comprehensive and structured reference manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Alphabetically arranged, the book provides direct, step-by-step guidance on core technical terminology, beginning with foundational definitions in criminalistics, law enforcement administration, and criminal law and jurisprudence. Beyond a standard dictionary layout, it emphasizes contextual academic application, equipping readers with critical techniques to distinguish between closely related legal terms, technical jargon, and specialized jargon used in correctional administration and sociology of crimes. Furthermore, the handbook extends into cross-disciplinary concepts—teaching essential vocabulary across scientific crime detection, operational police procedures, and court-admissible evidence frameworks—alongside practical study aids necessary for rapid recall during board exam preparations. Ultimately, this manual serves as an essential, highly functional reference aimed at untangling dense legal and technical language, equipping future criminologists with necessary linguistic precision, and promoting standardized, professional communication within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fbccb6670e1.34117859.jpg', '2026-05-22 10:17:47'),
(42, 'Criminalistics Criminal Justice Education Comprehensive Digest', 'Ricardo M. Guevara', '', 'Fil Crim.', '364.7', 'Am484', '2014', NULL, 'copies', 1, 'Criminology', 'Available', 1, 1, 0.0, 'The Criminology Glossary by Ricardo M. Guevara is a comprehensive and structured reference manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Alphabetically arranged, the book provides direct, step-by-step guidance on core technical terminology, beginning with foundational definitions in criminalistics, law enforcement administration, and criminal law and jurisprudence. Beyond a standard dictionary layout, it emphasizes contextual academic application, equipping readers with critical techniques to distinguish between closely related legal terms, technical jargon, and specialized jargon used in correctional administration and sociology of crimes. Furthermore, the handbook extends into cross-disciplinary concepts—teaching essential vocabulary across scientific crime detection, operational police procedures, and court-admissible evidence frameworks—alongside practical study aids necessary for rapid recall during board exam preparations. Ultimately, this manual serves as an essential, highly functional reference aimed at untangling dense legal and technical language, equipping future criminologists with necessary linguistic precision, and promoting standardized, professional communication within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fbd5c7502d2.91805557.jpg', '2026-05-22 10:20:12'),
(43, 'Criminal Justice System Basic Concepts and Approaches', 'Porferio C. Madelo Jr., Ph.D.', '', 'Fil Crim.', '364.9', 'M295', '2013', NULL, 'copies', 1, 'Criminology', 'Available', 1, 1, 0.0, 'The Criminology Glossary by Ricardo M. Guevara is a comprehensive and structured reference manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Alphabetically arranged, the book provides direct, step-by-step guidance on core technical terminology, beginning with foundational definitions in criminalistics, law enforcement administration, and criminal law and jurisprudence. Beyond a standard dictionary layout, it emphasizes contextual academic application, equipping readers with critical techniques to distinguish between closely related legal terms, technical jargon, and specialized jargon used in correctional administration and sociology of crimes. Furthermore, the handbook extends into cross-disciplinary concepts—teaching essential vocabulary across scientific crime detection, operational police procedures, and court-admissible evidence frameworks—alongside practical study aids necessary for rapid recall during board exam preparations. Ultimately, this manual serves as an essential, highly functional reference aimed at untangling dense legal and technical language, equipping future criminologists with necessary linguistic precision, and promoting standardized, professional communication within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fbdb376a187.69686509.jpg', '2026-05-22 10:21:39'),
(44, 'A Textbook on Comparative Police and Criminal Justice System', 'Mario A. Garcia', '', 'Fil Crim.', '363.2', 'G934', '2015', NULL, 'copies', 4, 'Criminology', 'Available', 4, 4, 0.0, 'The Criminology Glossary by Ricardo M. Guevara is a comprehensive and structured reference manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Alphabetically arranged, the book provides direct, step-by-step guidance on core technical terminology, beginning with foundational definitions in criminalistics, law enforcement administration, and criminal law and jurisprudence. Beyond a standard dictionary layout, it emphasizes contextual academic application, equipping readers with critical techniques to distinguish between closely related legal terms, technical jargon, and specialized jargon used in correctional administration and sociology of crimes. Furthermore, the handbook extends into cross-disciplinary concepts—teaching essential vocabulary across scientific crime detection, operational police procedures, and court-admissible evidence frameworks—alongside practical study aids necessary for rapid recall during board exam preparations. Ultimately, this manual serves as an essential, highly functional reference aimed at untangling dense legal and technical language, equipping future criminologists with necessary linguistic precision, and promoting standardized, professional communication within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fbe79dfd7e7.24940833.jpg', '2026-05-22 10:24:57'),
(45, 'Ethics and Values for Law Enforces', 'Guevara', '', 'Fil Crim.', '363.2', 'G934', '2014', NULL, 'copies', 4, 'Criminology', 'Available', 4, 4, 0.0, 'The Criminology Glossary by Ricardo M. Guevara is a comprehensive and structured reference manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Alphabetically arranged, the book provides direct, step-by-step guidance on core technical terminology, beginning with foundational definitions in criminalistics, law enforcement administration, and criminal law and jurisprudence. Beyond a standard dictionary layout, it emphasizes contextual academic application, equipping readers with critical techniques to distinguish between closely related legal terms, technical jargon, and specialized jargon used in correctional administration and sociology of crimes. Furthermore, the handbook extends into cross-disciplinary concepts—teaching essential vocabulary across scientific crime detection, operational police procedures, and court-admissible evidence frameworks—alongside practical study aids necessary for rapid recall during board exam preparations. Ultimately, this manual serves as an essential, highly functional reference aimed at untangling dense legal and technical language, equipping future criminologists with necessary linguistic precision, and promoting standardized, professional communication within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fbf08b3c0b4.79147579.jpg', '2026-05-22 10:27:20'),
(46, 'Basic Accounting Single Proprietorship', 'G.V. Lising, Jr., G.L. Lising, J.L. Lising', '', 'Fil', '657', 'L769', '1998', NULL, 'part', 1, 'Accounting', 'Available', 10, 10, 0.0, 'The Basic Accounting Part 1: Single Proprietorship by G.V. Lising, Jr., G.L. Lising, and J.L. Lising is a comprehensive and structured instructional manual designed primarily for Business Administration students, accountancy trainees, and aspiring entrepreneurs in the Philippines. Spanning foundational financial frameworks, the book provides direct, step-by-step guidance on core accounting principles, beginning with the definition of business organizations, the identification of the elements of the accounting equation, and the basic rules of debits and credits. Beyond theoretical definitions, it emphasizes practical procedural application, equipping readers with critical techniques to navigate the complete accounting cycle—specifically focusing on analyzing transactions, journalizing in general journals, and posting entries to general ledgers for a service or merchandising sole proprietorship. Furthermore, the handbook extends into financial reporting—teaching essential skills like preparing worksheets, formulating adjusting and closing entries, and constructing standard financial statements such as the Income Statement and Balance Sheet—alongside internal control concepts necessary for accurate cash and inventory management. Ultimately, this manual serves as an essential, highly functional reference aimed at presenting accounting concepts in a simplified, logical approach, equipping future professionals with rigorous bookkeeping capabilities, and promoting sound, data-driven financial management within the local business environment.', 'IMG ASSETS/library_covers/cover_6a0fc208cf7105.46861675.jpg', '2026-05-22 10:40:08'),
(47, 'Income Taxation', 'WIN Ballada, CPA, MBA', '', 'Fil', '336.24', 'B188', '2014', NULL, 'copies', 2, 'Accounting', 'Available', 2, 2, 0.0, 'The Basic Accounting by G.V. Lising, Jr., G.L. Lising, and J.L. Lising is a comprehensive and structured instructional manual designed primarily for Business Administration students, accountancy trainees, and aspiring entrepreneurs in the Philippines. Spanning foundational financial frameworks, the book provides direct, step-by-step guidance on core accounting principles, beginning with the definition of business organizations, the identification of the elements of the accounting equation, and the basic rules of debits and credits. Beyond theoretical definitions, it emphasizes practical procedural application, equipping readers with critical techniques to navigate the complete accounting cycle—specifically focusing on analyzing transactions, journalizing in general journals, and posting entries to general ledgers for a service or merchandising business. Furthermore, the handbook extends into financial reporting—teaching essential skills like preparing worksheets, formulating adjusting and closing entries, and constructing standard financial statements such as the Income Statement and Balance Sheet—alongside internal control concepts necessary for accurate cash and inventory management. Ultimately, this manual serves as an essential, highly functional reference aimed at presenting accounting concepts in a simplified, logical approach, equipping future professionals with rigorous bookkeeping capabilities, and promoting sound, data-driven financial management within the local business environment.', 'IMG ASSETS/library_covers/cover_6a0fc2c24247c2.07845935.jpg', '2026-05-22 10:43:14'),
(48, 'Analytical Geometry and Calculus with Statistics', 'Norita L. Lachenal, Gemma E. Rivera, Albert Flores', '', 'Fil', '515.15', 'L137', '2007', NULL, 'copies', 4, 'Accounting', 'Available', 4, 4, 0.0, 'The Analytical Geometry and Calculus with Statistics by Norita L. Lachenal, Gemma E. Rivera, and Albert Flores is a comprehensive and structured instructional worktext designed primarily for college students, mathematics majors, and engineering or business trainees in the Philippines. Integrating three major fields of mathematical sciences into a cohesive curriculum, the book provides direct, step-by-step guidance on core mathematical principles, beginning with the foundations of analytic geometry, including coordinate systems, straight lines, conic sections, and the geometric properties of algebraic equations. Beyond foundational plotting, it emphasizes practical calculus methodologies, equipping readers with critical techniques to master differential and integral calculus—specifically focusing on limits, continuity, rules of differentiation, and basic integration techniques applied to optimization and area problems. Furthermore, the handbook extends into data processing and analysis—teaching essential skills like organization of data, measures of central tendency and variability, and fundamental probability distributions—alongside statistical interpretation necessary for quantitative research and problem-solving. Ultimately, this manual serves as an essential, highly functional reference aimed at simplifying complex mathematical structures into logical, step-by-step lessons, equipping future professionals with rigorous quantitative capabilities, and promoting empirical, data-driven analytical skills within higher education.', 'IMG ASSETS/library_covers/cover_6a0fc3800f08a4.63338361.jpg', '2026-05-22 10:46:24'),
(49, 'Modern College Algebra', 'Feliciano and Uy', '', 'Fil', '332.4', 'L214', '2015', NULL, 'copies', 3, 'Accounting', 'Available', 3, 3, 0.0, 'The Modern College Algebra by Florentino T. Feliciano and Mariano B. Uy is a classic, highly structured instructional textbook designed primarily for college students, mathematics majors, and engineering or science trainees in the Philippines. Spanning fundamental algebraic systems, the book provides direct, step-by-step guidance on core mathematical principles, beginning with the structure of the real number system, the properties of exponents and radicals, and the systematic simplification of complex algebraic expressions. Beyond foundational arithmetic operations, it emphasizes practical algebraic execution, equipping readers with critical techniques to solve linear, quadratic, and higher-degree equations, master the methods of factoring, and evaluate complex systems of linear equations using determinants and matrices. Furthermore, the handbook extends into advanced mathematical sequences—teaching essential skills like understanding polynomial functions, manipulating logarithmic and exponential functions, and analyzing arithmetic and geometric progressions—alongside the principles of mathematical induction necessary for higher-level calculus preparation. Ultimately, this manual serves as an essential, time-tested reference aimed at building a rigorous mathematical foundation, equipping future professionals with analytical problem-solving capabilities, and promoting deep quantitative literacy within the local academic curriculum.', 'IMG ASSETS/library_covers/cover_6a0fc401ee5586.06994609.jpg', '2026-05-22 10:48:33'),
(50, 'Fundamental Statistics for College Students', 'Cristobal M. Pagoso, George Garcia', '', 'Fil', '519.2', 'P139', '1978', NULL, 'copies', 4, 'Accounting', 'Available', 4, 4, 0.0, 'The Fundamental Statistics for College Students by Cristobal M. Pagoso and George Garcia is a comprehensive and structured instructional textbook designed primarily for undergraduate college students, research trainees, and business majors in the Philippines. Spanning foundational statistical frameworks, the book provides direct, step-by-step guidance on core quantitative principles, beginning with the distinction between descriptive and inferential statistics, data collection methods, and the systematic organization of raw data into frequency distributions. Beyond foundational data plotting, it emphasizes practical mathematical methodologies, equipping readers with critical techniques to compute and interpret measures of central tendency (mean, median, mode), measures of dispersion (range, variance, standard deviation), and measures of skewness and kurtosis. Furthermore, the handbook extends into introductory inferential analysis—teaching essential skills like understanding fundamental probability concepts, applying normal curve distributions, and executing basic hypothesis testing using t-tests, z-tests, and chi-square tests—alongside linear correlation and regression analysis necessary for academic research. Ultimately, this manual serves as an essential, highly functional reference aimed at presenting statistical concepts in an accessible, step-by-step manner, equipping future professionals with rigorous data-processing capabilities, and promoting empirical, evidence-based research within higher education.', 'IMG ASSETS/library_covers/cover_6a0fc496582fc4.67229757.jpg', '2026-05-22 10:51:02'),
(51, 'Biological Science for College Students', 'David Cababaro Bueno, EdD', '', 'Fil', '570', 'B928', '2011', NULL, 'copies', 3, 'Biology', 'Available', 3, 3, 0.0, 'The Biological Science for College Students by David Cababaro Bueno, EdD is a comprehensive and structured instructional textbook designed primarily for undergraduate college students, general science majors, and allied health trainees in the Philippines. Spanning foundational life sciences, the book provides direct, step-by-step guidance on core biological principles, beginning with the chemical basis of life, the structural and functional organization of the cell, and the metabolic pathways of cellular respiration and photosynthesis. Beyond basic cellular definitions, it emphasizes practical ecological and evolutionary frameworks, equipping readers with critical techniques to analyze genetics and inheritance, the mechanisms of evolution, and the classification of the five kingdoms of life. Furthermore, the handbook extends into human anatomy and physiology—teaching essential skills like understanding organ systems, maintaining homeostatic balance, and recognizing the impact of human activities on biodiversity and ecosystem conservation—alongside bioethical considerations necessary for scientific literacy. Ultimately, this manual serves as an essential, highly functional reference aimed at presenting complex biological mechanisms in an accessible, localized context, equipping future professionals with rigorous scientific capabilities, and promoting deep environmental stewardship and empirical inquiry within higher education.', 'IMG ASSETS/library_covers/cover_6a0fc5230c67f6.67974235.jpg', '2026-05-22 10:53:23'),
(52, 'Mathematics of Investment', 'Rogelio M. Hernandez, Antonio C. Sta. Maria, Pastor B. Malaborbor, Lorina G. Salamat', '', 'fil', '510.76', 'M426', '1995', NULL, 'copies', 10, 'Accounting', 'Available', 10, 10, 0.0, 'The Mathematics of Investment by Rogelio M. Hernandez, Antonio C. Sta. Maria, Pastor B. Malaborbor, and Lorina G. Salamat is a comprehensive and structured instructional textbook designed primarily for Business Administration, Accountancy, and Finance students in the Philippines. Spanning foundational financial-mathematical frameworks, the book provides direct, step-by-step guidance on core quantitative principles, beginning with the theories of simple interest and simple discount, the concept of the time value of money, and the exact and approximate methods of counting days. Beyond foundational computations, it emphasizes practical financial execution, equipping readers with critical techniques to analyze and solve complex compound interest problems, evaluate nominal and effective rates, and manage various types of annuities, including ordinary, deferred, and perpetuity models. Furthermore, the handbook extends into long-term financial obligations—teaching essential skills like constructing amortization schedules, calculating sinking funds, and assessing bond valuations and depreciation methods—alongside the administrative math necessary for capital budgeting and investment appraisal. Ultimately, this manual serves as an essential, highly functional reference aimed at converting dense corporate finance concepts into clear, calculable operations, equipping future financial professionals with rigorous mathematical capabilities, and promoting sound, data-driven fiscal management within the local business environment.', 'IMG ASSETS/library_covers/cover_6a0fc5b2973537.46801851.jpg', '2026-05-22 10:55:46'),
(53, 'Fundamentals of Criminology', 'Rommel K. Manwong', '', 'Fil Crim.', '364', 'M295', '2013', NULL, 'copies', 3, 'Criminology', 'Available', 3, 3, 0.0, 'The Fundamentals of Criminology by Rommel K. Manwong is a comprehensive and structured instructional manual designed primarily for Criminology students, law enforcement trainees, and board exam reviewees in the Philippines. Spanning foundational criminological frameworks, the book provides direct, step-by-step guidance on core theoretical principles, beginning with the historical evolution of criminology, the nature and classification of crimes, and the legal parameters that define criminal behavior. Beyond theoretical definitions, it emphasizes practical behavioral and societal applications, equipping readers with critical techniques to analyze the major schools of thought—specifically contrasting the Classical, Positivist, and Chicago schools—to understand the underlying etiologies of crime. Furthermore, the handbook extends into the operational mechanics of social control—teaching essential concepts like victimology, the psychology of criminal conduct, and the interaction of the criminal justice pillars—alongside the administrative methodologies necessary for modern crime prevention. Ultimately, this manual serves as an essential, highly functional reference aimed at synthesizing complex behavioral and legal frameworks into accessible lessons, equipping future criminologists with deep analytical capabilities, and promoting standardized, professional insights within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fc68ee34141.86070777.jpg', '2026-05-22 10:59:26'),
(54, 'Advanced Techniques in Dactyloscopy with AFIS', 'Jesus Bueno Vinluan Ph.D. Crim , Jose M. Buenavista Jr., M.S. Crim', '', 'Fil Crim.', '363.258', 'V768', '2012', NULL, 'copies', 4, 'Criminology', 'Available', 4, 4, 0.0, 'The Advanced Techniques in Dactyloscopy with AFIS by Dr. Jesus Bueno Vinluan and Jose M. Buenavista Jr., M.S. Crim is a comprehensive and structured instructional manual designed primarily for Criminology students, forensic technicians, and law enforcement professionals in the Philippines. Spanning advanced biometric and personal identification frameworks, the book provides direct, step-by-step guidance on core dactyloscopic principles, beginning with the anatomy of friction skin, ridge characteristics (minutiae), and the standard Galton-Henry system of fingerprint classification. Beyond foundational ink-and-paper tracking, it emphasizes practical modern applications, equipping readers with critical techniques to detect, develop, and lift latent prints using specialized chemical reagents and powders at crime scenes. Furthermore, the handbook extends into digitized forensic technology—teaching essential skills like enrolling, searching, and matching digital prints using the Automated Fingerprint Identification System (AFIS)—alongside the legal protocols necessary for presenting fingerprint evidence and expert testimony in Philippine courts. Ultimately, this manual serves as an essential, highly functional reference aimed at bridging traditional forensic methods with modern biometric automation, equipping future criminologists with deep technical capabilities, and promoting empirical precision within the criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fc71a96b203.01904977.jpg', '2026-05-22 11:01:46'),
(55, 'Revised Rules on Evidence', 'Atty. Bernardo M. Luceres', '', 'Fil Crim.', '349.9149', 'L935', '2012', NULL, 'copies', 2, 'Criminology', 'Available', 2, 2, 0.0, 'The Advanced Techniques in Dactyloscopy with AFIS by Dr. Jesus Bueno Vinluan and Jose M. Buenavista Jr., M.S. Crim is a comprehensive and structured instructional manual designed primarily for Criminology students, forensic technicians, and law enforcement professionals in the Philippines. Spanning advanced biometric and personal identification frameworks, the book provides direct, step-by-step guidance on core dactyloscopic principles, beginning with the anatomy of friction skin, ridge characteristics (minutiae), and the standard Galton-Henry system of fingerprint classification. Beyond foundational ink-and-paper tracking, it emphasizes practical modern applications, equipping readers with critical techniques to detect, develop, and lift latent prints using specialized chemical reagents and powders at crime scenes. Furthermore, the handbook extends into digitized forensic technology—teaching essential skills like enrolling, searching, and matching digital prints using the Automated Fingerprint Identification System (AFIS)—alongside the legal protocols necessary for presenting fingerprint evidence and expert testimony in Philippine courts. Ultimately, this manual serves as an essential, highly functional reference aimed at bridging traditional forensic methods with modern biometric automation, equipping future criminologists with deep technical capabilities, and promoting empirical precision within the criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fc7b1d7dd51.44378893.jpg', '2026-05-22 11:04:17'),
(56, 'Criminalistics A Review for Criminology Based Education', 'Dr. Edwin T. Montalba', '', 'Fil Crim.', '345.599', 'M762', '2014', NULL, 'copies', 4, 'Criminology', 'Available', 4, 4, 0.0, 'The Criminalistics: A Review for Criminology-Based Education by Dr. Edwin T. Montalba is a comprehensive and structured instructional review manual designed primarily for Criminology students, forensic trainees, and board exam reviewees in the Philippines. Spanning the core scientific disciplines of forensic science, the book provides direct, step-by-step guidance on criminalistics principles, beginning with the historical foundations, legal admissibility, and proper preservation of physical evidence at a crime scene. Beyond theoretical overviews, it emphasizes practical laboratory and field applications, equipping readers with critical techniques to master the six traditional areas of criminalistics: Personal Identification (Dactyloscopy), Forensic Photography, Forensic Ballistics, Questioned Document Examination, Polygraphy (Lie Detection), and Legal Medicine. Furthermore, the handbook extends into contemporary investigative technologies—teaching essential skills like understanding DNA profiling, chain of custody protocols, and forensic report writing—alongside exam-targeted rationalizations necessary for passing the Criminologist Licensure Examination (CLE). Ultimately, this manual serves as an essential, highly functional reference aimed at synthesizing complex scientific procedures into highly scannable, high-yield lessons, equipping future criminologists with deep technical capabilities, and promoting empirical precision within the Philippine criminal justice system.', 'IMG ASSETS/library_covers/cover_6a0fc85d2457e6.30969830.jpg', '2026-05-22 11:07:09');
INSERT INTO `library_books` (`id`, `title`, `author`, `isbn`, `lcc_letter_line`, `lcc_class_number`, `lcc_cutter_code`, `lcc_published_year`, `lcc_additional_info`, `additional_info_type`, `additional_info_quantity`, `category`, `status`, `total_copies`, `available_copies`, `rating`, `overview`, `cover_url`, `created_at`) VALUES
(57, 'Managerial Accounting Creating Value in a Dynamic Business Environment', 'Hilton Platt', '', 'GC', '658.1511', 'H656', '2015', NULL, 'copies', 1, 'Accounting', 'Available', 1, 1, 0.0, 'The Managerial Accounting: Creating Value in a Dynamic Business Environment by Ronald W. Hilton and David E. Platt is a comprehensive and structured instructional textbook designed primarily for Business Administration, Accountancy, and Management students globally. Spanning contemporary and traditional corporate finance frameworks, the book provides direct, step-by-step guidance on core strategic principles, beginning with the changing role of managerial accounting in a globalized market, cost classifications, and the fundamental differences between managerial and financial accounting. Beyond theoretical definitions, it emphasizes practical procedural application, equipping readers with critical techniques to navigate cost management systems—specifically focusing on job-order costing, process costing, and modern tools such as activity-based costing (ABC) and throughput costing to track manufacturing and service operations. Furthermore, the handbook extends into strategic decision-making and operational control—teaching essential skills like cost-volume-profit (CVP) analysis, tactical budgeting, variance analysis, capital expenditure evaluation, and the development of Balanced Scorecards—alongside customer profitability analysis necessary for performance evaluation. Ultimately, this textbook serves as an essential, highly functional reference aimed at showing how financial data actively creates organizational value, equipping future corporate professionals with rigorous cost management capabilities, and promoting sound, data-driven managerial execution within highly dynamic business environments.', 'IMG ASSETS/library_covers/cover_6a0fc8f1d55c30.48066976.jpg', '2026-05-22 11:09:37'),
(58, 'Introduction to Cost Accounting', 'Maryanne M. Mowen, Don R. Hansen', '', 'GC', '657.42', 'M936', '2013', NULL, 'copies', 1, 'Accounting', 'Available', 1, 1, 0.0, 'The Introduction to Cost Accounting by Maryanne M. Mowen and Don R. Hansen is a comprehensive and structured instructional textbook designed primarily for Accountancy, Business Administration, and Management Accounting students. Spanning foundational and contemporary cost management frameworks, the book provides direct, step-by-step guidance on core fiscal principles, beginning with the classification of costs, the behavior of fixed and variable expenses, and the structural differences between cost accounting, financial accounting, and managerial accounting. Beyond theoretical definitions, it emphasizes practical procedural application, equipping readers with critical techniques to navigate product costing systems—specifically focusing on measuring and assigning costs through job-order costing, process costing, and activity-based costing (ABC) models within manufacturing and service sectors. Furthermore, the handbook extends into operational planning and control—teaching essential skills like cost-volume-profit (CVP) analysis, the preparation of master and flexible budgets, standard costing with variance analysis, and segment performance evaluation—alongside tactical decision-making strategies necessary for short-term differential analysis. Ultimately, this textbook serves as an essential, highly functional reference aimed at transforming complex internal financial tracking into logical, structured lessons, equipping future corporate professionals with rigorous cost accounting capabilities, and promoting sound, data-driven operational control within the global business environment.', 'IMG ASSETS/library_covers/cover_6a0fc964b46137.81619130.jpg', '2026-05-22 11:11:32'),
(59, 'Partnership and Corporations', 'Fidelito R. Soriano', '', 'Fil', '346.0682', 'So714', '2015', NULL, 'copies', 1, 'Accounting', 'Available', 1, 1, 0.0, 'The Partnership and Corporations (often subtitled Notes and Cases) by Fidelito R. Soriano, CPA, is a comprehensive and structured instructional manual designed primarily for Accountancy and Business Administration students, law trainees, and board exam reviewees in the Philippines. Spanning foundational business law and accounting frameworks, the book provides direct, step-by-step guidance on core legal and financial principles, beginning with the legal definitions, characteristics, and classifications of partnerships and corporations under the Civil Code and the Revised Corporation Code of the Philippines. Beyond theoretical statutory provisions, it emphasizes practical procedural application, equipping readers with critical techniques to navigate the complete accounting and operational lifecycles—specifically focusing on the formation, operations, division of profits and losses, dissolution, and liquidation of partnerships. Furthermore, the handbook extends into corporate equity structures—teaching essential skills like journalizing share capital transactions, accounting for treasury shares and retained earnings, processing dividends, and preparing the stockholders\' equity section of the financial statements—alongside standard corporate governance and dissolution protocols. Ultimately, this manual serves as an essential, highly functional reference aimed at synthesizing complex legal codes and financial entries into highly structured, logical lessons, equipping future professionals with rigorous bookkeeping and legal compliance capabilities, and promoting sound, data-driven organizational management within the Philippine business environment.', 'IMG ASSETS/library_covers/cover_6a0fc9f32fc6d1.91946900.jpg', '2026-05-22 11:13:55'),
(60, 'Cost Accounting Principles and Procedural Applications', 'Pedro M. Guerrero', '', 'Fil', '657.42', 'G934', '2010', NULL, 'volume', 1, 'Accounting', 'Available', 2, 2, 0.0, 'The Cost Accounting: Principles and Procedural Applications (Volume 2) by Pedro M. Guerrero is a comprehensive and structured instructional textbook designed primarily for Accountancy, Management Accounting, and Business Administration students in the Philippines. Spanning advanced cost management and specialized operational frameworks, the book provides direct, step-by-step guidance on core specialized principles, beginning with the accounting for joint products and by-products, the allocation of common costs, and the standard costing system. Beyond foundational product costing, it emphasizes practical procedural application, equipping readers with critical techniques to navigate complex manufacturing environments—specifically focusing on analyzing material, labor, and overhead variances, as well as mastering the mechanics of the piece-rate system and standard cost ledger entries. Furthermore, the handbook extends into strategic planning, control, and decision-making—teaching essential skills like cost-volume-profit (CVP) analysis for multi-product firms, gross profit variance analysis, segment reporting, and differential cost analysis for tactical decisions such as making or buying components, accepting special orders, or shutting down departments. Ultimately, this textbook serves as an essential, highly functional reference aimed at transforming intricate internal financial data into logical, structured lessons, equipping future financial professionals with rigorous cost control capabilities, and promoting sound, data-driven managerial execution within the local and global corporate sectors.', 'IMG ASSETS/library_covers/cover_6a0fcb5f852a25.40620915.jpg', '2026-05-22 11:19:59'),
(61, 'Cost Accounting', 'Guillermo M. De Leon Jr., Norma D. De Leon', '', 'Fil', '657.42', 'D346', '2004', NULL, 'copies', 1, 'Accounting', 'Available', 1, 1, 0.0, 'The Cost Accounting by Guillermo M. De Leon Jr. and Norma D. De Leon is a comprehensive and structured instructional textbook designed primarily for Accountancy, Management Accounting, and Business Administration students in the Philippines. Spanning foundational cost management frameworks, the book provides direct, step-by-step guidance on core financial principles, beginning with the classification of costs, the elements of manufacturing costs (direct materials, direct labor, and factory overhead), and the flow of costs through the financial statements of a manufacturing enterprise. Beyond theoretical definitions, it emphasizes practical procedural application, equipping readers with critical techniques to navigate product costing systems—specifically focusing on the mechanics of job-order costing, process costing (including equivalent units of production under FIFO and average methods), and the accounting for spoilage, waste, defective units, and scrap. Furthermore, the handbook extends into operational control and overhead management—teaching essential skills like the accumulation, allocation, and distribution of service department costs using direct, step, and reciprocal methods, alongside the analysis of factory overhead variances. Ultimately, this textbook serves as an essential, highly functional reference aimed at transforming complex internal bookkeeping and industrial tracking into logical, structured lessons, equipping future financial professionals with rigorous cost accounting capabilities, and promoting precise, data-driven operational management within the local corporate sector.', 'IMG ASSETS/library_covers/cover_6a0fd0609fc7f3.83667903.jpg', '2026-05-22 11:41:20'),
(62, 'Fundamentals of Accounting', 'Robinson P. Chu', '', 'Fil', '657.044', 'C559', '1999', NULL, 'part', 2, 'Accounting', 'Available', 13, 13, 0.0, 'The Fundamentals of Accounting by Robinson P. Chu is a comprehensive and structured instructional textbook designed primarily for Accountancy, Business Administration, and Senior High School ABM (Accountancy, Business, and Management) students in the Philippines. Spanning foundational financial frameworks, the book provides direct, step-by-step guidance on core bookkeeping principles, beginning with the definition, evolution, and basic branches of accounting, the conceptual framework of financial reporting, and the fundamental accounting equation ($Assets = Liabilities + Owner\'s\\ Equity$).Beyond theoretical definitions, it emphasizes practical procedural application, equipping readers with critical techniques to navigate the complete accounting cycle for service and merchandising businesses—specifically focusing on analyzing business transactions, journalizing entries in general and special journals, posting to the general and subsidiary ledgers, and preparing a trial balance.Furthermore, the handbook extends into period-end reporting and adjustments—teaching essential skills like computing adjusting entries for accruals, deferrals, depreciation, and bad debts, utilizing a worksheet, and constructing fundamental financial statements (Statement of Financial Position, Income Statement, and Statement of Cash Flows) alongside the execution of closing entries and post-closing trial balances.Ultimately, this textbook serves as an essential, highly functional reference aimed at transforming raw financial data and basic commercial tracking into logical, structured lessons, equipping future financial professionals with rigorous technical bookkeeping capabilities, and promoting sound, data-driven financial literacy within the local business and academic sectors.', '', '2026-05-22 11:43:53'),
(63, 'Intermediate Financial Accounting Part 2', 'Zeus Vernon B. Millan', '', 'Fil', '657.0461', 'M645', '2015', NULL, 'part', 2, 'Accounting', 'Available', 2, 2, 0.0, 'The Intermediate Financial Accounting (often published across multiple volumes) by Zeus Vernon B. Millan is a comprehensive, highly authoritative, and structured instructional textbook designed primarily for Accountancy and Management Accounting students, CPA reviewees, and financial professionals in the Philippines. Spanning advanced financial reporting frameworks aligned strictly with the Philippine Financial Reporting Standards (PFRS) and International Financial Reporting Standards (IFRS), the book provides direct, step-by-step guidance on core accounting principles, beginning with the conceptual framework for financial reporting and the rigorous valuation, classification, and presentation of asset accounts such as cash and cash equivalents, receivables, inventories, and property, plant, and equipment (', 'IMG ASSETS/library_covers/cover_6a0fd21317efe3.61833086.jpg', '2026-05-22 11:48:35'),
(64, 'Financial Reporting Theory and Practice', 'Zeus Vernon B. Millan', '', 'Fil', '657.046', 'M645', '2003', NULL, 'part', 1, 'Accounting', 'Available', 1, 1, 0.0, 'The Financial Reporting Theory and Practice by Zeus Vernon B. Millan is a comprehensive, highly authoritative, and structured instructional textbook designed primarily for Accountancy and Management Accounting students, CPA licensure exam reviewees, and financial auditors in the Philippines. Spanning advanced financial reporting frameworks aligned strictly with the Philippine Financial Reporting Standards (PFRS) and International Financial Reporting Standards (IFRS), the book provides direct, step-by-step guidance on core accounting theories, beginning with the Conceptual Framework for Financial Reporting, the qualitative characteristics of useful financial information, and the recognition and measurement criteria for financial statement elements.', 'IMG ASSETS/library_covers/cover_6a0fd2ba26af96.61202037.jpg', '2026-05-22 11:51:22'),
(65, 'Principles of Marketing', 'Kurtz Boone', '', 'GC', '658.85', 'K96', '2011', NULL, 'copies', 1, 'Business', 'Available', 1, 1, 0.0, 'The Principles of Marketing by David L. Kurtz and Louis E. Boone is a comprehensive, globally renowned, and highly structured instructional textbook designed primarily for Business Administration, Marketing Management, and Entrepreneurship students. Spanning contemporary and traditional commerce frameworks, the book provides direct, step-by-step guidance on core strategic principles, beginning with the definition of marketing, the evolution of the marketing concept, and the systematic analysis of the macro- and micro-marketing environments.', 'IMG ASSETS/library_covers/cover_6a0fd4465b56e1.23790918.jpg', '2026-05-22 11:57:58'),
(66, 'Advanced Accounting', 'Ma. Violeta V. Vicente', '', 'Fil', '657.076', 'V632', '2000', NULL, 'volume', 1, 'Accounting', 'Available', 3, 3, 0.0, 'The Advanced Accounting (often published across multiple volumes) by Ma. Violeta V. Vicente is a comprehensive, highly authoritative, and structured instructional textbook designed primarily for Accountancy and Management Accounting students, CPA reviewees, and financial controllers in the Philippines. Spanning complex and specialized accounting frameworks aligned with the Philippine Financial Reporting Standards (PFRS), the book provides direct, step-by-step guidance on core advanced corporate transactions, beginning with the accounting for business combinations (mergers and acquisitions) under the acquisition method, and the preparation of consolidated financial statements for parent and subsidiary corporations.', 'IMG ASSETS/library_covers/cover_6a0fd4d34d3515.43321928.jpg', '2026-05-22 12:00:19'),
(67, 'The Law on Business Organization', 'Justo P. Torres Jr.', '', 'Fil', '346.06', 'T693', '2008', NULL, 'copies', 1, 'Business', 'Available', 1, 1, 0.0, 'The The Law on Business Organizations by Justo P. Torres Jr. is a comprehensive, highly authoritative, and structured instructional textbook designed primarily for Accountancy, Business Administration, and law students in the Philippines. Spanning foundational corporate and commercial law frameworks aligned with the Civil Code and the Revised Corporation Code of the Philippines, the book provides direct, step-by-step guidance on core legal principles, beginning with the legal definitions, attributes, and classifications of various business vehicles.', 'IMG ASSETS/library_covers/cover_6a0fd591d28412.03278312.jpg', '2026-05-22 12:03:29'),
(68, 'Philippine Taxes on Transfer and Business', 'Llamado, San Diego, Tamayo', '', 'Fil', '336.27', 'L791', '1998', NULL, 'copies', 17, 'Business', 'Available', 17, 17, 0.0, 'The Philippine Taxes on Transfer and Business by Manuel L. Llamado, Raymond A. San Diego, and Christopher R. Tamayo is a comprehensive, highly authoritative, and structured instructional manual designed primarily for Accountancy and Business Administration students, CPA reviewees, and tax practitioners in the Philippines. Spanning national internal revenue frameworks aligned strictly with the National Internal Revenue Code (NIRC), as amended by recent tax reform laws like the TRAIN Law and the CREATE Act, the book provides direct, step-by-step guidance on core taxation principles, beginning with the distinction between transfer taxes and business taxes, the nature of estate taxation, and the determination of the gross estate and allowable deductions for citizens, residents, and non-resident aliens.', 'IMG ASSETS/library_covers/cover_6a0fd61f5822d3.98447213.jpg', '2026-05-22 12:05:51'),
(69, 'The Law and Obligation\'s and Contracts', 'Hector S. De Leon, Hector M. De Leon Jr.', '', 'Fil', '146.59902', 'D346', '2014', NULL, 'copies', 7, 'Law', 'Available', 7, 6, 0.0, 'The The Law on Obligations and Contracts by Hector S. De Leon and Hector M. De Leon Jr. is a comprehensive, highly authoritative, and structured instructional textbook designed primarily for Accountancy, Business Administration, and law students in the Philippines. Spanning foundational civil law frameworks aligned strictly with Title I to Title V of Book IV of the Civil Code of the Philippines, the book provides direct, step-by-step guidance on core legal principles, beginning with the definition, essential elements, and sources of obligations (law, contracts, quasi-contracts, acts or omissions punished by law, and quasi-delicts).', 'IMG ASSETS/library_covers/cover_6a0fd6a4752b94.69819768.jpg', '2026-05-22 12:08:04'),
(70, 'Organization and Management', 'Ranulfo P. Payos, Ernesto G. Espinosa, Orlando S. Zorilla', '', 'SH', '658', 'P346', '2016', NULL, 'copies', 1, 'Business', 'Available', 1, 1, 0.0, 'The Organization and Management by Ranulfo P. Payos, Ernesto G. Espinosa, and Orlando S. Zorilla is a comprehensive, authoritative, and highly structured instructional textbook designed primarily for Business Administration, Management, and Senior High School ABM (Accountancy, Business, and Management) students in the Philippines. Spanning core administrative and organizational behavior frameworks, the book provides direct, step-by-step guidance on fundamental corporate principles, beginning with the definition of management, its evolution through classical and modern theories, and the critical role managers play in local and global business environments.', 'IMG ASSETS/library_covers/cover_6a0fd790472a43.39259047.jpg', '2026-05-22 12:12:00'),
(71, 'Speech and Oral Communication in Action', 'Renato De Guzman Rosales, Myrna Gagalang Sulit, Matilda Heralde Dimaano', '', 'Fil', '428', 'D321', '2017', NULL, 'copies', 1, 'Verbal', 'Available', 1, 1, 0.0, 'The Speech and Oral Communication in Action by Renato De Guzman Rosales, Myrna Gagalang Sulit, and Matilda Heralde Dimaano is a comprehensive, highly interactive, and structured instructional textbook designed primarily for College students, Public Speaking trainees, and Mass Communication majors in the Philippines. Spanning fundamental and advanced rhetorical frameworks, the book provides direct, step-by-step guidance on core linguistic principles, beginning with the linear, interactive, and transactional models of communication, the physiological process of speech production, and the critical barriers that cause communication breakdown.', 'IMG ASSETS/library_covers/cover_6a0fd9314b9470.80750543.jpg', '2026-05-22 12:18:57'),
(72, '21st Century Literature from the Philippines and the World', 'Paulyn Alejer Datu, Primier, Jan Allen A. Pascua, Wilchie Dane H. Olayres', '', 'SH', '808.8', 'D234', '2017', NULL, 'copies', 4, 'K-12', 'Available', 5, 5, 0.0, 'The 21st Century Literature from the Philippines and the World by Paulyn Alejer Datu, Jan Allen A. Pascua, and Wilchie Dane H. Olayres is a comprehensive, culturally immersive, and highly structured instructional textbook designed primarily for Senior High School and undergraduate students in the Philippines. Spanning contemporary literary movements and historical milestones, the book provides direct, step-by-step guidance on analyzing modern text, beginning with the canonical traditions of Philippine literature from the pre-colonial, Spanish, American, and Japanese eras, and transitioning into the vibrant landscapes of 21st-century writing.', 'IMG ASSETS/library_covers/cover_6a0fda2ca37065.66029374.jpg', '2026-05-22 12:23:08'),
(73, 'Essential Mathematics for the Modern World', 'Rizaldi C. Nocon, Ederlina G. Nocon', '', 'Fil', '510', 'N75', '2018', NULL, 'copies', 4, 'Accounting', 'Available', 4, 4, 0.0, 'The Essential Mathematics for the Modern World by Rizaldi C. Nocon and Ederlina G. Nocon is a comprehensive, highly engaging, and structured instructional textbook designed primarily for undergraduate college students in the Philippines, explicitly aligned with the Commission on Higher Education (CHED) curriculum for Mathematics in the Modern World. Spanning foundational and contemporary mathematical frameworks, the book provides direct, step-by-step guidance on core quantitative principles, beginning with the appreciation of mathematics as a language, the recognition of patterns in nature, and the application of inductive and deductive reasoning to solve complex puzzles and logic problems.', 'IMG ASSETS/library_covers/cover_6a0fdabe8a4151.19332868.jpg', '2026-05-22 12:25:34'),
(74, 'Purposive Communication', 'Hannah P. Mariveles', '', 'SH', '372.4', 'M343', '2016', NULL, 'copies', 2, 'K-12', 'Available', 2, 2, 0.0, 'The Purposive Communication by Hannah P. Mariveles is a comprehensive, highly interactive, and structured instructional textbook designed primarily for undergraduate college students in the Philippines, explicitly aligned with the Commission on Higher Education (CHED) General Education curriculum. Spanning foundational and contemporary communication frameworks, the book provides direct, step-by-step guidance on core socio-linguistic principles, beginning with the nature and elements of communication, the importance of cultural awareness, and the impact of globalization on intercultural interactions.', 'IMG ASSETS/library_covers/cover_6a0fdbceb73434.00784139.jpg', '2026-05-22 12:30:06'),
(75, 'Medical Transcription', 'Joanette G. Rodrigo', '', 'R', '728.8', 'R62', '2017', NULL, 'copies', 2, 'K-12', 'Available', 2, 1, 0.0, 'The Medical Transcription by Joanette G. Rodrigo is a comprehensive, highly technical, and structured instructional manual designed primarily for Health Information Management students, Allied Health professionals, and aspiring medical transcriptionists in the Philippines. Spanning foundational healthcare documentation and administrative frameworks, the book provides direct, step-by-step guidance on core transcription principles, beginning with the role and responsibilities of a medical transcriptionist, medicolegal considerations regarding patient confidentiality (such as HIPAA and local health data privacy laws), and the mechanics of the medical transcription workflow.', 'IMG ASSETS/library_covers/cover_6a0fdc6d0a0165.08001810.jpg', '2026-05-22 12:32:45'),
(76, 'Physical Education and Health', 'Lualhati Fernando-Callo, Peter Fermin Dajime', '', 'SH', '613.7', 'F363', '2016', NULL, 'volume', 1, 'K-12', 'Available', 1, 1, 0.0, 'The Physical Education and Health (often published as a multi-volume series for senior high school) by Lualhati Fernando-Callo and Peter Fermin Dajime is a comprehensive, highly active, and structured instructional textbook designed primarily for Senior High School and undergraduate students in the Philippines, strictly aligned with the Department of Education (DepEd) K-to-12 curriculum. Spanning foundational fitness and holistic wellness frameworks, the book provides direct, step-by-step guidance on core health principles, beginning with the self-assessment of health-related and skill-related fitness components, the computation of Body Mass Index (BMI), and the execution of proper optimization plans for energy systems.', 'IMG ASSETS/library_covers/cover_6a0fdd45ebf3e7.94045303.jpg', '2026-05-22 12:36:21'),
(88, 'TestBook2', 'TestBook21', '', 'HE', '8700.7', 'P6T44', '1983', NULL, 'volume', NULL, 'Accounting', 'Available', 3, 3, 0.0, 'SAASA', '', '2026-06-09 09:40:42'),
(90, 'asa', 'sasa', '', 'as', 'sa', 'sa', '333', NULL, 'copies', NULL, 'Criminology', 'Borrowed', 0, 0, 0.0, 'sas', '', '2026-06-09 10:03:08');

-- --------------------------------------------------------

--
-- Table structure for table `library_book_copies`
--

CREATE TABLE `library_book_copies` (
  `id` int(10) UNSIGNED NOT NULL,
  `book_id` int(10) UNSIGNED NOT NULL,
  `copy_type` enum('volume','part','number','copies') NOT NULL DEFAULT 'copies',
  `copy_number` int(11) NOT NULL,
  `identifier` varchar(50) NOT NULL,
  `lcc_letter_line` varchar(10) DEFAULT NULL,
  `lcc_class_number` varchar(50) DEFAULT NULL,
  `lcc_cutter_code` varchar(50) DEFAULT NULL,
  `lcc_published_year` varchar(10) DEFAULT NULL,
  `lcc_additional_info` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_book_copies`
--

INSERT INTO `library_book_copies` (`id`, `book_id`, `copy_type`, `copy_number`, `identifier`, `lcc_letter_line`, `lcc_class_number`, `lcc_cutter_code`, `lcc_published_year`, `lcc_additional_info`, `created_at`) VALUES
(11, 7, 'volume', 2, 'V2', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(12, 7, 'volume', 3, 'V3', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(13, 7, 'volume', 4, 'V4', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(14, 7, 'volume', 5, 'V5', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(15, 7, 'volume', 6, 'V6', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(16, 7, 'volume', 7, 'V7', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(17, 7, 'volume', 8, 'V8', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(18, 7, 'volume', 9, 'V9', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(19, 7, 'volume', 10, 'V10', NULL, NULL, NULL, NULL, NULL, '2026-04-29 11:10:44'),
(136, 33, 'copies', 1, 'C1', 'Fil Crim', '363.2', 'V632', '2015', NULL, '2026-05-22 09:56:40'),
(137, 33, 'copies', 2, 'C2', 'Fil Crim', '363.2', 'V632', '2015', NULL, '2026-05-22 09:56:40'),
(138, 33, 'copies', 3, 'C3', 'Fil Crim', '363.2', 'V632', '2015', NULL, '2026-05-22 09:56:40'),
(139, 33, 'copies', 4, 'C4', 'Fil Crim', '363.2', 'V632', '2015', NULL, '2026-05-22 09:56:40'),
(140, 34, 'copies', 1, 'C1', 'Fil Crim.', '364.7', 'F727', '2012', NULL, '2026-05-22 10:01:36'),
(141, 34, 'copies', 2, 'C2', 'Fil Crim.', '364.7', 'F727', '2012', NULL, '2026-05-22 10:01:36'),
(142, 36, 'copies', 1, 'C1', 'Fil Crim.', '363.23', 'F632', '2012', NULL, '2026-05-22 10:05:23'),
(143, 37, 'copies', 1, 'C1', 'Fil Crim.', '364.7', 'G216', '2015', NULL, '2026-05-22 10:07:22'),
(144, 37, 'copies', 2, 'C2', 'Fil Crim.', '364.7', 'G216', '2015', NULL, '2026-05-22 10:07:22'),
(145, 38, 'copies', 1, 'C1', 'Fil Crim.', '362.254', 'V714', '2014', NULL, '2026-05-22 10:10:02'),
(146, 38, 'copies', 2, 'C2', 'Fil Crim.', '362.254', 'V714', '2014', NULL, '2026-05-22 10:10:02'),
(147, 38, 'copies', 3, 'C3', 'Fil Crim.', '362.254', 'V714', '2014', NULL, '2026-05-22 10:10:02'),
(148, 38, 'copies', 4, 'C4', 'Fil Crim.', '362.254', 'V714', '2014', NULL, '2026-05-22 10:10:02'),
(149, 38, 'copies', 5, 'C5', 'Fil Crim.', '362.254', 'V714', '2014', NULL, '2026-05-22 10:10:02'),
(150, 38, 'copies', 6, 'C6', 'Fil Crim.', '362.254', 'V714', '2014', NULL, '2026-05-22 10:10:02'),
(151, 39, 'copies', 1, 'C1', 'Fil Crim.', '364.4', 'Ed24', '2016', NULL, '2026-05-22 10:12:29'),
(152, 39, 'copies', 2, 'C2', 'Fil Crim.', '364.4', 'Ed24', '2016', NULL, '2026-05-22 10:12:29'),
(153, 39, 'copies', 3, 'C3', 'Fil Crim.', '364.4', 'Ed24', '2016', NULL, '2026-05-22 10:12:29'),
(154, 39, 'copies', 4, 'C4', 'Fil Crim.', '364.4', 'Ed24', '2016', NULL, '2026-05-22 10:12:29'),
(155, 40, 'copies', 1, 'C1', 'Ref Crim.', '364.3', 'G939', '2014', NULL, '2026-05-22 10:14:44'),
(156, 41, 'copies', 1, 'C1', 'Fil Crim.', '364.7', 'Am484', '2014', NULL, '2026-05-22 10:17:47'),
(157, 42, 'copies', 1, 'C1', 'Fil Crim.', '364.7', 'Am484', '2014', NULL, '2026-05-22 10:20:12'),
(158, 43, 'copies', 1, 'C1', 'Fil Crim.', '364.9', 'M295', '2013', NULL, '2026-05-22 10:21:39'),
(159, 44, 'copies', 1, 'C1', 'Fil Crim.', '363.2', 'G934', '2015', NULL, '2026-05-22 10:24:57'),
(160, 44, 'copies', 2, 'C2', 'Fil Crim.', '363.2', 'G934', '2015', NULL, '2026-05-22 10:24:57'),
(161, 44, 'copies', 3, 'C3', 'Fil Crim.', '363.2', 'G934', '2015', NULL, '2026-05-22 10:24:57'),
(162, 44, 'copies', 4, 'C4', 'Fil Crim.', '363.2', 'G934', '2015', NULL, '2026-05-22 10:24:57'),
(163, 45, 'copies', 1, 'C1', 'Fil Crim.', '363.2', 'G934', '2014', NULL, '2026-05-22 10:27:20'),
(164, 45, 'copies', 2, 'C2', 'Fil Crim.', '363.2', 'G934', '2014', NULL, '2026-05-22 10:27:20'),
(165, 45, 'copies', 3, 'C3', 'Fil Crim.', '363.2', 'G934', '2014', NULL, '2026-05-22 10:27:20'),
(166, 45, 'copies', 4, 'C4', 'Fil Crim.', '363.2', 'G934', '2014', NULL, '2026-05-22 10:27:20'),
(167, 46, 'part', 1, 'P1', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:08'),
(168, 46, 'part', 1, 'P1-2', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(169, 46, 'part', 1, 'P1-3', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(170, 46, 'part', 1, 'P1-4', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(171, 46, 'part', 1, 'P1-5', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(172, 46, 'part', 1, 'P1-6', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(173, 46, 'part', 1, 'P1-7', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(174, 46, 'part', 1, 'P1-8', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(175, 46, 'part', 1, 'P1-9', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(176, 46, 'part', 1, 'P1-10', 'Fil', '657', 'L769', '1998', NULL, '2026-05-22 10:40:35'),
(177, 47, 'copies', 1, 'C1', 'Fil', '336.24', 'B188', '2014', NULL, '2026-05-22 10:43:14'),
(178, 47, 'copies', 2, 'C2', 'Fil', '336.24', 'B188', '2014', NULL, '2026-05-22 10:43:14'),
(179, 48, 'copies', 1, 'C1', 'Fil', '515.15', 'L137', '2007', NULL, '2026-05-22 10:46:24'),
(180, 48, 'copies', 2, 'C2', 'Fil', '515.15', 'L137', '2007', NULL, '2026-05-22 10:46:24'),
(181, 48, 'copies', 3, 'C3', 'Fil', '515.15', 'L137', '2007', NULL, '2026-05-22 10:46:24'),
(182, 48, 'copies', 4, 'C4', 'Fil', '515.15', 'L137', '2007', NULL, '2026-05-22 10:46:24'),
(183, 49, 'copies', 1, 'C1', 'Fil', '332.4', 'L214', '2015', NULL, '2026-05-22 10:48:33'),
(184, 49, 'copies', 2, 'C2', 'Fil', '332.4', 'L214', '2015', NULL, '2026-05-22 10:48:33'),
(185, 49, 'copies', 3, 'C3', 'Fil', '332.4', 'L214', '2015', NULL, '2026-05-22 10:48:33'),
(186, 50, 'copies', 1, 'C1', 'Fil', '519.2', 'P139', '1978', NULL, '2026-05-22 10:51:02'),
(187, 50, 'copies', 2, 'C2', 'Fil', '519.2', 'P139', '1978', NULL, '2026-05-22 10:51:02'),
(188, 50, 'copies', 3, 'C3', 'Fil', '519.2', 'P139', '1978', NULL, '2026-05-22 10:51:02'),
(189, 50, 'copies', 4, 'C4', 'Fil', '519.2', 'P139', '1978', NULL, '2026-05-22 10:51:02'),
(190, 51, 'copies', 1, 'C1', 'Fil', '570', 'B928', '2011', NULL, '2026-05-22 10:53:23'),
(191, 51, 'copies', 2, 'C2', 'Fil', '570', 'B928', '2011', NULL, '2026-05-22 10:53:23'),
(192, 51, 'copies', 3, 'C3', 'Fil', '570', 'B928', '2011', NULL, '2026-05-22 10:53:23'),
(193, 52, 'copies', 1, 'C1', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(194, 52, 'copies', 2, 'C2', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(195, 52, 'copies', 3, 'C3', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(196, 52, 'copies', 4, 'C4', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(197, 52, 'copies', 5, 'C5', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(198, 52, 'copies', 6, 'C6', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(199, 52, 'copies', 7, 'C7', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(200, 52, 'copies', 8, 'C8', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(201, 52, 'copies', 9, 'C9', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(202, 52, 'copies', 10, 'C10', 'fil', '510.76', 'M426', '1995', NULL, '2026-05-22 10:55:46'),
(203, 53, 'copies', 1, 'C1', 'Fil Crim.', '364', 'M295', '2013', NULL, '2026-05-22 10:59:26'),
(204, 53, 'copies', 2, 'C2', 'Fil Crim.', '364', 'M295', '2013', NULL, '2026-05-22 10:59:26'),
(205, 53, 'copies', 3, 'C3', 'Fil Crim.', '364', 'M295', '2013', NULL, '2026-05-22 10:59:26'),
(206, 54, 'copies', 1, 'C1', 'Fil Crim.', '363.258', 'V768', '2012', NULL, '2026-05-22 11:01:46'),
(207, 54, 'copies', 2, 'C2', 'Fil Crim.', '363.258', 'V768', '2012', NULL, '2026-05-22 11:01:46'),
(208, 54, 'copies', 3, 'C3', 'Fil Crim.', '363.258', 'V768', '2012', NULL, '2026-05-22 11:01:46'),
(209, 54, 'copies', 4, 'C4', 'Fil Crim.', '363.258', 'V768', '2012', NULL, '2026-05-22 11:01:46'),
(210, 55, 'copies', 1, 'C1', 'Fil Crim.', '349.9149', 'L935', '2012', NULL, '2026-05-22 11:04:17'),
(211, 55, 'copies', 2, 'C2', 'Fil Crim.', '349.9149', 'L935', '2012', NULL, '2026-05-22 11:04:17'),
(212, 56, 'copies', 1, 'C1', 'Fil Crim.', '345.599', 'M762', '2014', NULL, '2026-05-22 11:07:09'),
(213, 56, 'copies', 2, 'C2', 'Fil Crim.', '345.599', 'M762', '2014', NULL, '2026-05-22 11:07:09'),
(214, 56, 'copies', 3, 'C3', 'Fil Crim.', '345.599', 'M762', '2014', NULL, '2026-05-22 11:07:09'),
(215, 56, 'copies', 4, 'C4', 'Fil Crim.', '345.599', 'M762', '2014', NULL, '2026-05-22 11:07:09'),
(216, 57, 'copies', 1, 'C1', 'GC', '658.1511', 'H656', '2015', NULL, '2026-05-22 11:09:37'),
(217, 58, 'copies', 1, 'C1', 'GC', '657.42', 'M936', '2013', NULL, '2026-05-22 11:11:32'),
(218, 59, 'copies', 1, 'C1', 'Fil', '346.0682', 'So714', '2015', NULL, '2026-05-22 11:13:55'),
(219, 60, 'volume', 1, 'V1', 'Fil', '657.42', 'G934', '2010', NULL, '2026-05-22 11:19:59'),
(220, 60, 'volume', 2, 'V2', 'Fil', '657.42', 'G934', '2010', NULL, '2026-05-22 11:20:10'),
(221, 61, 'copies', 1, 'C1', 'Fil', '657.42', 'D346', '2004', NULL, '2026-05-22 11:41:20'),
(222, 62, 'part', 1, 'P1', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:43:53'),
(223, 62, 'part', 2, 'P2', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:43:53'),
(224, 62, 'part', 2, 'P2-2', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(225, 62, 'part', 2, 'P2-3', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(226, 62, 'part', 2, 'P2-4', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(227, 62, 'part', 2, 'P2-5', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(228, 62, 'part', 2, 'P2-6', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(229, 62, 'part', 2, 'P2-7', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(230, 62, 'part', 2, 'P2-8', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(231, 62, 'part', 2, 'P2-9', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(232, 62, 'part', 2, 'P2-10', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(233, 62, 'part', 2, 'P2-11', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(234, 62, 'part', 2, 'P2-12', 'Fil', '657.044', 'C559', '1999', NULL, '2026-05-22 11:45:49'),
(235, 63, 'part', 1, 'P1', 'Fil', '657.0461', 'M645', '2015', NULL, '2026-05-22 11:48:35'),
(236, 63, 'part', 2, 'P2', 'Fil', '657.0461', 'M645', '2015', NULL, '2026-05-22 11:48:35'),
(237, 64, 'part', 1, 'P1', 'Fil', '657.046', 'M645', '2003', NULL, '2026-05-22 11:51:22'),
(238, 65, 'copies', 1, 'C1', 'GC', '658.85', 'K96', '2011', NULL, '2026-05-22 11:57:58'),
(239, 66, 'volume', 1, 'V1', 'Fil', '657.076', 'V632', '2000', NULL, '2026-05-22 12:00:19'),
(240, 66, 'volume', 1, 'V1-2', 'Fil', '657.076', 'V632', '2000', NULL, '2026-05-22 12:00:36'),
(241, 66, 'volume', 1, 'V1-3', 'Fil', '657.076', 'V632', '2000', NULL, '2026-05-22 12:00:36'),
(242, 67, 'copies', 1, 'C1', 'Fil', '346.06', 'T693', '2008', NULL, '2026-05-22 12:03:29'),
(243, 68, 'copies', 1, 'C1', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(244, 68, 'copies', 2, 'C2', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(245, 68, 'copies', 3, 'C3', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(246, 68, 'copies', 4, 'C4', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(247, 68, 'copies', 5, 'C5', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(248, 68, 'copies', 6, 'C6', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(249, 68, 'copies', 7, 'C7', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(250, 68, 'copies', 8, 'C8', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(251, 68, 'copies', 9, 'C9', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(252, 68, 'copies', 10, 'C10', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(253, 68, 'copies', 11, 'C11', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(254, 68, 'copies', 12, 'C12', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(255, 68, 'copies', 13, 'C13', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(256, 68, 'copies', 14, 'C14', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(257, 68, 'copies', 15, 'C15', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(258, 68, 'copies', 16, 'C16', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(259, 68, 'copies', 17, 'C17', 'Fil', '336.27', 'L791', '1998', NULL, '2026-05-22 12:05:51'),
(260, 69, 'copies', 1, 'C1', 'Fil', '146.59902', 'D346', '2014', NULL, '2026-05-22 12:08:04'),
(261, 69, 'copies', 2, 'C2', 'Fil', '146.59902', 'D346', '2014', NULL, '2026-05-22 12:08:04'),
(262, 69, 'copies', 3, 'C3', 'Fil', '146.59902', 'D346', '2014', NULL, '2026-05-22 12:08:04'),
(263, 69, 'copies', 4, 'C4', 'Fil', '146.59902', 'D346', '2014', NULL, '2026-05-22 12:08:04'),
(264, 69, 'copies', 5, 'C5', 'Fil', '146.59902', 'D346', '2014', NULL, '2026-05-22 12:08:04'),
(265, 69, 'copies', 6, 'C6', 'Fil', '146.59902', 'D346', '2014', NULL, '2026-05-22 12:08:04'),
(266, 69, 'copies', 7, 'C7', 'Fil', '146.59902', 'D346', '2014', NULL, '2026-05-22 12:08:04'),
(267, 70, 'copies', 1, 'C1', 'SH', '658', 'P346', '2016', NULL, '2026-05-22 12:12:00'),
(268, 71, 'copies', 1, 'C1', 'Fil', '428', 'D321', '2017', NULL, '2026-05-22 12:18:57'),
(269, 72, 'copies', 1, 'C1', 'SH', '808.8', 'D234', '2017', NULL, '2026-05-22 12:23:08'),
(270, 72, 'copies', 2, 'C2', 'SH', '808.8', 'D234', '2017', NULL, '2026-05-22 12:23:08'),
(271, 72, 'copies', 3, 'C3', 'SH', '808.8', 'D234', '2017', NULL, '2026-05-22 12:23:08'),
(272, 72, 'copies', 4, 'C4', 'SH', '808.8', 'D234', '2017', NULL, '2026-05-22 12:23:08'),
(273, 73, 'copies', 1, 'C1', 'Fil', '510', 'N75', '2018', NULL, '2026-05-22 12:25:34'),
(274, 73, 'copies', 2, 'C2', 'Fil', '510', 'N75', '2018', NULL, '2026-05-22 12:25:34'),
(275, 73, 'copies', 3, 'C3', 'Fil', '510', 'N75', '2018', NULL, '2026-05-22 12:25:34'),
(276, 73, 'copies', 4, 'C4', 'Fil', '510', 'N75', '2018', NULL, '2026-05-22 12:25:34'),
(277, 74, 'copies', 1, 'C1', 'SH', '372.4', 'M343', '2016', NULL, '2026-05-22 12:30:06'),
(278, 74, 'copies', 2, 'C2', 'SH', '372.4', 'M343', '2016', NULL, '2026-05-22 12:30:06'),
(279, 75, 'copies', 1, 'C1', 'R', '728.8', 'R62', '2017', NULL, '2026-05-22 12:32:45'),
(280, 75, 'copies', 2, 'C2', 'R', '728.8', 'R62', '2017', NULL, '2026-05-22 12:32:45'),
(281, 76, 'volume', 1, 'V1', 'SH', '613.7', 'F363', '2016', NULL, '2026-05-22 12:36:21'),
(282, 88, 'volume', 8, 'V8', 'HE', '8700.7', 'P6T44', '1983', NULL, '2026-06-09 09:40:42'),
(283, 88, 'volume', 6, 'V6', 'HE', '8700.7', 'P6T44', '1983', NULL, '2026-06-09 09:40:42'),
(284, 88, 'volume', 10, 'V10', 'HE', '8700.7', 'P6T44', '1983', NULL, '2026-06-09 09:52:27'),
(352, 72, 'copies', 5, 'C5', 'SH', '808.8', 'D234', '2017', NULL, '2026-06-09 10:34:26');

-- --------------------------------------------------------

--
-- Table structure for table `library_borrows`
--

CREATE TABLE `library_borrows` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `book_id` int(10) UNSIGNED NOT NULL,
  `borrow_date` datetime NOT NULL,
  `due_date` datetime NOT NULL,
  `returned_at` datetime DEFAULT NULL,
  `status` enum('Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
  `condition_status` enum('Good','Damaged','Lost') NOT NULL DEFAULT 'Good',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `borrow_days` int(11) NOT NULL DEFAULT 3,
  `copy_number` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_borrows`
--

INSERT INTO `library_borrows` (`id`, `user_id`, `book_id`, `borrow_date`, `due_date`, `returned_at`, `status`, `condition_status`, `created_at`, `borrow_days`, `copy_number`) VALUES
(36, 36, 7, '2026-05-27 10:30:46', '2026-05-30 17:00:00', '2026-06-03 18:31:53', 'Returned', 'Good', '2026-05-27 10:30:46', 3, 0),
(37, 36, 66, '2026-05-27 10:31:25', '2026-05-30 17:00:00', '2026-06-04 18:14:59', 'Returned', 'Good', '2026-05-27 10:31:25', 3, 1),
(38, 36, 75, '2026-05-31 10:16:01', '2026-06-03 17:00:00', NULL, 'Overdue', 'Good', '2026-05-31 10:16:01', 3, 0),
(39, 36, 69, '2026-05-31 10:18:16', '2026-06-03 17:00:00', NULL, 'Overdue', 'Good', '2026-05-31 10:18:16', 3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `library_calendar`
--

CREATE TABLE `library_calendar` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('holiday','no_class','graduation','exam','acquaintance','intramurals','others') NOT NULL DEFAULT 'no_class',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_calendar`
--

INSERT INTO `library_calendar` (`id`, `event_date`, `event_type`, `description`, `created_at`, `start_time`, `end_time`) VALUES
(63, '2026-09-14', 'others', '📣✨ HELLO, PASSians! ✨📣\r\nThe PRELIMINARY EXAMINATION SCHEDULE is here! 📝\r\nTake a little moment to check your exam schedule and plan your study time ahead. ⏰\r\nRemember: prepare well, stay calm, and believe in yourself — your hard work will surely pay off! 🌷', '2026-09-05 15:05:52', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `library_calendar_images`
--

CREATE TABLE `library_calendar_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `calendar_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_calendar_images`
--

INSERT INTO `library_calendar_images` (`id`, `calendar_id`, `image_path`, `created_at`) VALUES
(99, 63, 'IMG ASSETS/calendar_announcement_images/789161476_122128419380947939_3467044290726834287_n_1788591952_6435039e.jpg', '2026-09-05 15:05:52'),
(100, 63, 'IMG ASSETS/calendar_announcement_images/793204066_122128419434947939_8574229181886994718_n_1788591952_a999a62d.jpg', '2026-09-05 15:05:52'),
(101, 63, 'IMG ASSETS/calendar_announcement_images/792110192_122128421798947939_6342277832526486250_n_1788591952_f5abdc91.jpg', '2026-09-05 15:05:52'),
(102, 63, 'IMG ASSETS/calendar_announcement_images/793158909_122128419326947939_6894475652709005740_n_1788591952_b7ba3b55.jpg', '2026-09-05 15:05:52'),
(103, 63, 'IMG ASSETS/calendar_announcement_images/793248751_122128419350947939_8003837541672029085_n_1788591952_a73a9b50.jpg', '2026-09-05 15:05:52'),
(104, 63, 'IMG ASSETS/calendar_announcement_images/792149586_122128419626947939_3644340013136805007_n_1788591952_f5fbf33e.jpg', '2026-09-05 15:05:52'),
(105, 63, 'IMG ASSETS/calendar_announcement_images/792344052_122128419710947939_2250808166849978983_n_1788591952_b3b34462.jpg', '2026-09-05 15:05:52'),
(106, 63, 'IMG ASSETS/calendar_announcement_images/793204087_122128419800947939_2945905000538790931_n_1788591952_d6a650b0.jpg', '2026-09-05 15:05:52'),
(107, 63, 'IMG ASSETS/calendar_announcement_images/793203943_122128419842947939_4708316846393021486_n_1788591952_6414a5c1.jpg', '2026-09-05 15:05:52'),
(108, 63, 'IMG ASSETS/calendar_announcement_images/792110196_122128420004947939_108181697266482383_n_1788591952_b0edb822.jpg', '2026-09-05 15:05:52'),
(109, 63, 'IMG ASSETS/calendar_announcement_images/792149586_122128420046947939_4761417342220981376_n_1788591952_1163e605.jpg', '2026-09-05 15:05:52'),
(110, 63, 'IMG ASSETS/calendar_announcement_images/790425821_122128420166947939_2034698941854561669_n_1788591952_4f230e1b.jpg', '2026-09-05 15:05:52'),
(111, 63, 'IMG ASSETS/calendar_announcement_images/794266110_122128420208947939_2840054863873824149_n_1788591952_8abb27db.jpg', '2026-09-05 15:05:52');

-- --------------------------------------------------------

--
-- Table structure for table `library_categories`
--

CREATE TABLE `library_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_categories`
--

INSERT INTO `library_categories` (`id`, `name`, `created_at`) VALUES
(1, '12346', '2026-04-01 12:45:07'),
(2, 'YES', '2026-04-01 14:05:46'),
(4, 'BSCS', '2026-04-26 10:01:03'),
(5, 'BSBA', '2026-04-26 10:03:24'),
(7, 'BSCRIM', '2026-04-26 10:06:31'),
(8, 'Criminology', '2026-05-22 09:56:40'),
(9, 'Accounting', '2026-05-22 10:40:08'),
(10, 'Biology', '2026-05-22 10:53:23'),
(11, 'Business', '2026-05-22 11:57:58'),
(12, 'Law', '2026-05-22 12:08:04'),
(13, 'Verbal', '2026-05-22 12:18:57'),
(14, 'K-12', '2026-05-22 12:23:08');

-- --------------------------------------------------------

--
-- Table structure for table `library_config`
--

CREATE TABLE `library_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_config`
--

INSERT INTO `library_config` (`config_key`, `config_value`, `updated_at`) VALUES
('about_core_values', '[]', '2026-07-02 09:04:10'),
('about_mission', 'Empower every student with accessible learning resources, strong guidance, and a safe environment that encourages growth, innovation, and global competitiveness.', '2026-07-02 09:04:10'),
('about_people', '[{\"name\":\"ggg\",\"category\":\"Administration\",\"image\":\"..\\/assets\\/uploads\\/about_people\\/person-1782954309-0.jpg\"}]', '2026-07-02 09:05:09'),
('about_programs', '[{\"title\":\"\",\"description\":\"\",\"color\":\"#000000\"}]', '2026-07-02 09:04:44'),
('about_vision', 'To become a leading Higher Educational Institution committed to a holistic and transformative community through learning dedicated to academic excellence, leadership, and values formation.', '2026-07-02 09:04:10'),
('borrow_duration_school_days', '3', '2026-04-01 12:22:44'),
('footer_contacts', '[{\"label\":\"Main Office\",\"value\":\"pass.college.alaminos@gmail.com\"},{\"label\":\"Main Office\",\"value\":\"(075) 654 1020\"}]', '2026-09-05 14:45:10'),
('footer_locations', '[{\"name\":\"PASS College Main Building\",\"link\":\"https:\\/\\/maps.app.goo.gl\\/9LefCUAZs15DgJMr5\",\"address\":\"Quezon Ave, Poblacion, Alaminos City, 2404 Pangasinan\"},{\"name\":\"PASS College Annex Building\",\"link\":\"https:\\/\\/maps.app.goo.gl\\/f6Xi6mYjFDgBRMsd7\",\"address\":\"G\\/F, Raroque Building, Marcos Avenue, Alaminos City, 2404 Pangasinan\"}]', '2026-09-05 14:42:07'),
('footer_social', '[{\"platform\":\"Facebook Group\",\"link\":\"https:\\/\\/www.facebook.com\\/share\\/g\\/19dGL7pbwb\\/\"},{\"platform\":\"Facebook Page\",\"link\":\"https:\\/\\/www.facebook.com\\/passcollege\\/directory_intro\"}]', '2026-09-05 14:47:34'),
('login_logo_path', 'uploads/login_logo/login_logo_1782538547_a036826825.png', '2026-06-27 13:35:47'),
('max_active_reservations', '3', '2026-09-04 10:55:42'),
('max_borrowed_books', '4', '2026-05-04 14:45:58'),
('overdue_fine_per_day', '20.00', '2026-05-04 14:45:58'),
('reservation_hold_days', '2', '2026-04-01 12:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `library_feedback`
--

CREATE TABLE `library_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `borrow_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `book_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED DEFAULT 0,
  `book_review` text DEFAULT NULL,
  `service_feedback` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `book_rating` tinyint(3) UNSIGNED DEFAULT 0,
  `service_rating` tinyint(3) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_feedback`
--

INSERT INTO `library_feedback` (`id`, `borrow_id`, `user_id`, `book_id`, `rating`, `book_review`, `service_feedback`, `created_at`, `book_rating`, `service_rating`) VALUES
(1, 36, 36, 7, 0, 'agood', 'aaa', '2026-06-04 17:47:11', 4, 5);

-- --------------------------------------------------------

--
-- Table structure for table `library_fines`
--

CREATE TABLE `library_fines` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `borrow_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_fines`
--

INSERT INTO `library_fines` (`id`, `user_id`, `borrow_id`, `amount`, `paid`, `description`, `created_at`) VALUES
(14, 36, 36, 80.00, 0, 'Overdue fine for 4 day(s)', '2026-05-31 09:28:19'),
(15, 36, 37, 100.00, 0, 'Overdue fine for 5 day(s)', '2026-05-31 09:28:19'),
(16, 36, 38, 2220.00, 0, 'Overdue fine for 111 day(s)', '2026-06-03 10:25:43'),
(17, 36, 39, 2220.00, 0, 'Overdue fine for 111 day(s)', '2026-06-03 10:25:43');

-- --------------------------------------------------------

--
-- Table structure for table `library_notifications`
--

CREATE TABLE `library_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `reservation_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('reservation_ready','reservation_expired','reservation_on_hold','pickup_available') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_notifications`
--

INSERT INTO `library_notifications` (`id`, `user_id`, `reservation_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(149, 36, NULL, 'reservation_ready', 'Reservation Confirmed', 'You reserved \'To Kill a Mockingbird\'. Pick it up by May 27, 2026 5:00 +08:00May', 0, '2026-05-27 10:30:18'),
(150, 36, NULL, 'reservation_ready', 'Reservation Confirmed', 'You reserved \'Medical Transcription\'. Pick it up by May 31, 2026 5:00 +08:00May', 0, '2026-05-30 12:44:41'),
(151, 48, NULL, 'reservation_ready', 'Reservation Confirmed', 'You reserved \'Medical Transcription\'. Pick it up by May 31, 2026 5:00 +08:00May', 0, '2026-05-30 14:52:30'),
(152, 36, NULL, 'reservation_ready', 'Reservation Confirmed', 'You reserved \'To Kill a Mockingbird\'. Pick it up by Jun 04, 2026 5:00 +08:00Jun', 0, '2026-06-04 14:25:56'),
(153, 36, NULL, 'reservation_ready', 'Reservation Confirmed', 'You reserved \'The Law and Obligation\'s and Contracts\'. Pick it up by Jun 12, 2026 5:00 +08:00Jun', 0, '2026-06-12 08:47:34'),
(154, 36, NULL, '', 'Academic Promote', 'Congratulations! You have been promoted to the next academic level.', 0, '2026-06-19 16:34:27'),
(158, 59, NULL, '', 'Academic Promote', 'Congratulations! You have been promoted to the next academic level.', 0, '2026-06-20 09:21:46'),
(162, 46, NULL, '', 'Academic Promote', 'Congratulations! You have been promoted to the next academic level.', 0, '2026-06-20 11:07:48'),
(163, 36, 146, 'reservation_expired', 'Reservation Auto-Cancelled', 'Your reservation for \"To Kill a Mockingbird\" was automatically cancelled because the reservation hold time expired.', 0, '2026-06-21 11:56:39'),
(164, 36, 147, 'reservation_expired', 'Reservation Auto-Cancelled', 'Your reservation for \"The Law and Obligation\'s and Contracts\" was automatically cancelled because the reservation hold time expired.', 0, '2026-06-21 11:56:41'),
(166, 36, NULL, 'reservation_ready', 'Reservation Confirmed', 'You reserved \'Physical Education and Health\'. Pick it up by Sep 05, 2026 5:00 +08:00Sep', 0, '2026-09-04 10:56:28'),
(167, 36, 149, 'reservation_expired', 'Reservation Expired', 'Your reservation for the book expired. It was not picked up by the deadline.', 0, '2026-09-08 08:31:44');

-- --------------------------------------------------------

--
-- Table structure for table `library_open_access_resources`
--

CREATE TABLE `library_open_access_resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `course_year` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_verified` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_open_access_resources`
--

INSERT INTO `library_open_access_resources` (`id`, `name`, `category`, `course_year`, `description`, `url`, `is_active`, `created_by`, `created_at`, `updated_at`, `last_verified`) VALUES
(1, 'Philippine E-Journals', 'Philippine Research', NULL, 'The most essential source for local studies and academic publications in the Philippines.', 'https://ejournals.ph/', 1, 9, '2026-05-04 13:55:09', '2026-05-04 13:55:09', NULL),
(2, 'DOAJ', 'General Research', NULL, 'A comprehensive directory providing access to high-quality, peer-reviewed open access journals across all disciplines.', 'https://doaj.org/', 1, 9, '2026-05-04 13:56:11', '2026-05-04 13:56:11', NULL),
(3, 'Google Scholar', 'General Research', NULL, 'A powerful search engine for broadly searching scholarly literature, including articles, theses, and books.', 'https://scholar.google.com/', 1, 9, '2026-05-04 13:56:39', '2026-05-04 13:56:39', NULL),
(4, 'Scopus', 'General Research', NULL, 'A premier abstract and citation database for high-impact peer-reviewed literature and scientific journals.', 'https://www.scopus.com/', 1, 9, '2026-05-04 13:57:14', '2026-05-04 13:57:14', NULL),
(5, 'arXiv (Computer Science)', 'IT & Technology', NULL, 'A leading archive for the latest research papers in Artificial Intelligence (AI), software engineering, and hardware.', 'https://arxiv.org/archive/cs', 1, 9, '2026-05-04 13:58:00', '2026-05-04 13:58:00', NULL),
(6, 'OpenStax', 'Digital Textbooks', NULL, 'Offers free, peer-reviewed, and openly licensed college textbooks for various core subjects.', 'https://openstax.org/', 1, 9, '2026-05-04 13:58:38', '2026-05-04 13:58:38', NULL),
(7, 'Project Gutenberg', 'Classic Literature', NULL, 'A digital library of over 70,000 free eBooks, ideal for English and Literature majors focusing on public domain classics.', 'https://www.gutenberg.org/', 1, 9, '2026-05-04 13:59:10', '2026-05-04 13:59:10', NULL),
(8, 'SSRN', 'Social Sciences', NULL, 'A specialized repository for world-class research in social sciences, business, and humanities.', 'https://www.ssrn.com/', 1, 9, '2026-05-04 13:59:53', '2026-05-04 13:59:53', NULL),
(9, 'PubMed Central', 'Science & Health', NULL, 'A free archive of biomedical and life sciences journal literature at the U.S. National Institutes of Health.', 'https://www.ncbi.nlm.nih.gov/pmc/', 1, 9, '2026-05-04 14:00:19', '2026-05-04 14:00:19', NULL),
(10, 'PubMed Central', 'Science & Health', NULL, 'A free archive of biomedical and life sciences journal literature at the U.S. National Institutes of Health.', 'https://www.ncbi.nlm.nih.gov/pmc/', 1, 9, '2026-05-04 14:15:32', '2026-05-04 14:15:32', NULL),
(11, 'PubMed Central', 'Science & Health', NULL, 'A free archive of biomedical and life sciences journal literature at the U.S. National Institutes of Health.', 'https://www.ncbi.nlm.nih.gov/pmc/', 1, 9, '2026-05-04 14:20:26', '2026-05-04 14:20:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `library_reservations`
--

CREATE TABLE `library_reservations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `book_id` int(10) UNSIGNED NOT NULL,
  `reserved_at` datetime NOT NULL,
  `reserved_date` date NOT NULL,
  `pickup_date` date DEFAULT NULL,
  `queue_position` int(11) NOT NULL DEFAULT 1,
  `status` enum('Active','Completed','Cancelled') NOT NULL DEFAULT 'Active',
  `expires_at` datetime DEFAULT NULL,
  `pickup_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `additional_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_reservations`
--

INSERT INTO `library_reservations` (`id`, `user_id`, `book_id`, `reserved_at`, `reserved_date`, `pickup_date`, `queue_position`, `status`, `expires_at`, `pickup_until`, `created_at`, `additional_info`) VALUES
(143, 36, 7, '2026-05-27 10:30:18', '2026-05-27', '2026-05-27', 1, 'Completed', '2026-05-29 10:30:18', '2026-05-27 17:00:00', '2026-05-27 10:30:18', NULL),
(144, 36, 75, '2026-05-30 12:44:41', '2026-05-30', '2026-05-31', 1, 'Completed', '2026-06-01 12:44:41', '2026-05-31 17:00:00', '2026-05-30 12:44:41', NULL),
(145, 48, 75, '2026-05-30 14:52:30', '2026-05-30', '2026-05-31', 2, 'Cancelled', '2026-06-01 14:52:30', '2026-05-31 17:00:00', '2026-05-30 14:52:30', NULL),
(146, 36, 7, '2026-06-04 14:25:56', '2026-06-04', '2026-06-04', 1, 'Cancelled', '2026-06-06 14:25:56', '2026-06-04 17:00:00', '2026-06-04 14:25:56', NULL),
(147, 36, 69, '2026-06-12 08:47:34', '2026-06-12', '2026-06-12', 1, 'Cancelled', '2026-06-14 08:47:34', '2026-06-12 17:00:00', '2026-06-12 08:47:34', NULL),
(149, 36, 76, '2026-09-04 10:56:28', '2026-09-04', '2026-09-05', 1, 'Cancelled', '2026-09-06 10:56:28', '2026-09-05 17:00:00', '2026-09-04 10:56:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `library_resources`
--

CREATE TABLE `library_resources` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `course_year` varchar(100) DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `download_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_resources`
--

INSERT INTO `library_resources` (`id`, `title`, `description`, `category`, `course_year`, `file_url`, `file_name`, `file_size`, `file_type`, `uploaded_by`, `is_approved`, `approved_by`, `approved_at`, `download_count`, `created_at`) VALUES
(1, 'ayagfgd', 'muuuuuuuum', 'Computer Science', NULL, 'uploads/e-resources/69cddfbc14f80__SSS_(1).docx', '_SSS (1).docx', 36462, 'docx', NULL, 1, 9, '2026-04-02 12:00:12', 0, '2026-04-02 11:17:16'),
(2, 'sdgfsdfs', 'sfsdfsd', 'Computer Science', NULL, 'uploads/e-resources/69ce5e8542be1__SSS.docx', '_SSS.docx', 20882, 'docx', NULL, 1, 9, '2026-04-02 20:26:00', 0, '2026-04-02 20:18:13');

-- --------------------------------------------------------

--
-- Table structure for table `library_resource_categories`
--

CREATE TABLE `library_resource_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_resource_categories`
--

INSERT INTO `library_resource_categories` (`id`, `name`, `created_at`) VALUES
(1, 'Computer Science', '2026-04-02 11:53:08'),
(2, 'HOSPITALITY MANAGEMENT', '2026-05-04 14:20:26'),
(3, 'TOURISM MANAGEMENT', '2026-05-04 14:20:26'),
(4, 'EDUCATION', '2026-05-04 14:20:26'),
(5, 'CRIMINOLOGY', '2026-05-04 14:20:26'),
(6, 'ACCOUNTANCY', '2026-05-04 14:20:26'),
(7, 'BUSINESS ADMINISTRATION', '2026-05-04 14:20:26');

-- --------------------------------------------------------

--
-- Table structure for table `library_visits`
--

CREATE TABLE `library_visits` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `visitor_type` enum('student','teacher','visitor') NOT NULL DEFAULT 'visitor',
  `student_number` varchar(50) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `course_department` varchar(150) DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `checkin_method` varchar(50) NOT NULL DEFAULT 'qr',
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','in','out') NOT NULL DEFAULT 'pending',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_visits`
--

INSERT INTO `library_visits` (`id`, `user_id`, `name`, `visitor_type`, `student_number`, `employee_id`, `course_department`, `purpose`, `checkin_method`, `time_in`, `time_out`, `duration_minutes`, `created_at`, `status`, `is_approved`, `approved_by`, `approved_at`) VALUES
(1, 9, 'LEY', 'student', '34234234', NULL, 'CS', 'Reservation pickup', 'qr', '2026-04-02 08:44:45', '2026-04-02 10:44:45', 120, '2026-04-02 14:44:45', 'out', 1, 9, '2026-04-02 14:50:50'),
(2, 9, 'LEY', 'student', '34234234', NULL, 'CS', 'Reservation pickup', 'qr', '2026-04-02 08:51:03', '2026-04-02 14:22:03', 331, '2026-04-02 14:51:03', 'out', 1, 9, '2026-04-02 20:21:59'),
(3, 9, 'MNM', 'teacher', '00000', '123456', 'CS', 'Other', 'qr', '2026-04-10 09:12:08', '2026-04-10 09:12:58', 0, '2026-04-10 15:12:08', 'out', 1, 9, '2026-04-10 15:12:53'),
(4, 9, 'Rosed Des S', 'student', '34-23423', NULL, 'Bachelor of Science in Business Administration 3', 'Research', 'qr', '2026-04-13 04:19:00', '2026-04-13 06:19:00', 120, '2026-04-13 10:19:00', 'out', 1, 9, '2026-04-13 10:43:03'),
(5, 9, 'Dd', 'student', '34-2344', NULL, 'Bachelor of Science in Criminology 1', 'Research', 'qr', '2026-04-13 10:42:46', '2026-05-04 17:02:34', 379, '2026-04-13 10:42:46', 'out', 1, 9, '2026-04-13 10:46:11'),
(6, NULL, 'Ley', 'student', '56-66776', '23', 'Bachelor of Science in Accountancy 2', 'Reading', 'qr', '2026-04-13 14:11:10', '2026-05-04 17:02:38', 171, '2026-04-13 14:11:10', 'out', 1, 9, '2026-04-13 14:13:48'),
(7, NULL, 'Test User', 'student', 'TEST-001', NULL, 'Test Course', 'Testing', 'manual', '2026-05-04 16:47:55', '2026-05-04 17:00:00', 12, '2026-05-04 16:47:55', 'out', 0, NULL, NULL),
(8, NULL, 'Har Vie', 'student', '23-02044', NULL, 'Bachelor of Science in Hospitality Management 3', 'Reservation pickup', 'manual', '2026-05-04 16:53:39', '2026-05-04 17:00:00', 6, '2026-05-04 16:53:39', 'out', 0, NULL, NULL),
(9, NULL, 'Har Vie', 'student', '23-02044', NULL, 'Bachelor of Science in Hospitality Management 3', 'Reservation pickup', 'manual', '2026-05-04 17:00:00', '2026-05-04 17:00:00', 0, '2026-05-04 17:00:00', 'out', 0, NULL, NULL),
(10, NULL, 'Har Vie', 'student', '23-02044', NULL, 'Bachelor of Science in Hospitality Management 3', 'Reservation pickup', 'manual', '2026-05-04 17:02:12', '2026-05-04 17:00:00', -2, '2026-05-04 17:02:12', 'out', 0, NULL, NULL),
(11, NULL, 'Har Vie', 'student', '23-02044', NULL, 'Bachelor of Science in Hospitality Management 3', 'Borrowing', 'manual', '2026-05-06 20:41:34', '2026-05-12 11:08:04', 866, '2026-05-06 20:41:34', 'out', 0, NULL, NULL),
(12, NULL, 'Har Vie', 'student', '23-02044', NULL, 'Bachelor of Science in Hospitality Management 3', 'Research', 'manual', '2026-05-12 11:08:20', '2026-05-23 17:00:00', 16191, '2026-05-12 11:08:20', 'out', 0, NULL, NULL),
(13, NULL, 'Ley Harvie Barreto Peralta', 'student', '23-02036', NULL, 'Bachelor of Science in Computer Science 3', 'Borrowing', 'manual', '2026-05-27 10:31:55', '2026-05-28 13:13:56', 162, '2026-05-27 10:31:55', 'out', 0, NULL, NULL),
(14, NULL, 'Ley Harvie Barreto Peralta', 'student', '23-02036', NULL, 'Bachelor of Science in Computer Science 3', 'Reading', 'manual', '2026-05-28 13:15:43', '2026-05-28 13:17:01', 1, '2026-05-28 13:15:43', 'out', 0, NULL, NULL),
(15, NULL, 'Ley Harvie Barreto Peralta', 'student', '23-02036', NULL, 'Bachelor of Science in Computer Science 3', 'Reading', 'manual', '2026-05-28 13:16:44', '2026-05-28 13:17:08', 0, '2026-05-28 13:16:44', 'out', 0, NULL, NULL),
(16, 9, 'Elmar Rau Raut', 'student', '23-02044', NULL, 'Bachelor of Science in Computer Science 3', 'Research', 'qr', '2026-05-28 13:17:48', '2026-05-31 09:37:36', 1219, '2026-05-28 13:17:48', 'out', 1, 9, '2026-05-31 09:35:50'),
(17, NULL, 'Ley Harvie Barreto Peralta', 'student', '23-02036', NULL, 'Bachelor of Science in Computer Science 3', 'Reading', 'manual', '2026-05-28 13:18:16', '2026-05-31 09:37:07', 1218, '2026-05-28 13:18:16', 'out', 0, NULL, NULL),
(19, 9, 'Ley Harvie Barreto Peralta', 'student', '23-02044', NULL, 'Bachelor in Elementary Education 1', 'Borrowing', 'qr', '2026-05-31 09:48:08', '2026-05-31 11:48:08', 120, '2026-05-31 09:48:08', 'out', 1, 9, '2026-05-31 09:49:02'),
(20, 9, 'Ff', 'teacher', NULL, 'ff', 'f', 'Reservation pickup', 'qr', '2026-05-31 09:48:50', '2026-06-09 10:39:52', 51, '2026-05-31 09:48:50', 'out', 1, 9, '2026-06-09 10:39:47'),
(21, 9, 'Ley Harvie Barreto Peralta (TEST)', 'student', '23-02044', NULL, 'Bachelor in Elementary Education 1', 'Borrowing', 'manual', '2026-06-21 09:00:00', '2026-06-21 17:00:00', 480, '2026-06-21 12:14:12', 'out', 1, 9, '2026-06-21 06:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `pending_student_registrations`
--

CREATE TABLE `pending_student_registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `course_year` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`documents`)),
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_code` varchar(20) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `status` enum('pending_email','pending_admin','approved','rejected') NOT NULL DEFAULT 'pending_email',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(50) DEFAULT 'student',
  `employee_id` varchar(50) DEFAULT NULL,
  `is_head` tinyint(1) NOT NULL DEFAULT 0,
  `head_service` varchar(100) DEFAULT 'none',
  `admin_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pending_student_registrations`
--

INSERT INTO `pending_student_registrations` (`id`, `full_name`, `email`, `student_id`, `course_year`, `password_hash`, `documents`, `email_verified`, `verification_code`, `verification_expires`, `status`, `approved_by`, `approved_at`, `created_at`, `updated_at`, `role`, `employee_id`, `is_head`, `head_service`, `admin_type`) VALUES
(1, 'Har Dd Dd', 'kusurigoyakuzen@gmail.com', '22-22564', 'Bachelor of Science in Accountancy 2', '$2y$10$pcYeITqFwmWX2U36/5sGEeTL1769MZchjVUKX3fMNBjUEtFJXDE/u', '[\"uploads/registration/regdoc_69f870b8c1ba09.07409013.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-05-04 18:51:37', '2026-05-04 18:11:57', '2026-05-04 18:51:37', 'student', NULL, 0, 'none', NULL),
(2, 'Elmar Raut-raut', 'marrautraut@gmail.com', '23-02363', 'Bachelor of Science in Computer Science 1', '$2y$10$F11vYUuZKSIoNLQ4H8NV6e3SSkBU3Ef33jX8wOig8yAA/65PmZfDe', '[\"uploads\\/student_registrations\\/student_doc_6a195a175da280.14014360.png\",\"uploads\\/student_registrations\\/student_doc_6a195a175e1089.41015941.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-05-30 10:20:14', '2026-05-29 17:23:59', '2026-05-30 10:20:14', 'student', NULL, 0, 'none', NULL),
(3, 'Jasmin Arachea', 'aracheajasmin@gmail.com', '23-02320', 'Bachelor of Science in Computer Science 3', '$2y$10$Kz9QeZQCoAIYWoaHmZ.s5OzEBuJKwIaK.BpdVQ0BVGOuNa0NZthRu', '[\"uploads\\/student_registrations\\/student_doc_6a1a41d7cc0e43.86595555.png\",\"uploads\\/student_registrations\\/student_doc_6a1a41d7cc8c85.54563852.png\"]', 1, NULL, NULL, 'approved', 1, '2026-05-30 10:17:21', '2026-05-30 09:49:03', '2026-05-30 10:17:21', 'student', NULL, 0, 'none', NULL),
(4, 'Janelle Zamora', 'lakshal369@gmail.com', '23-03728', 'Bachelor of Science in Computer Science 3', '$2y$10$d8nk6SENQGX4KKJatJfhD.P1GyPD8hMEAxoG4sNe4QxRd7ckXj7dq', '[\"uploads/registration/regdoc_6a1a5a9906b4d7.51731151.png\",\"uploads/registration/regdoc_6a1a5a990711f4.37616606.png\"]', 1, NULL, NULL, 'approved', 1, '2026-07-16 08:29:38', '2026-05-30 11:34:48', '2026-07-16 08:29:38', 'student', NULL, 0, 'none', NULL),
(5, 'Q Q Q', 'leyperalta2@gmail.com', '22-22244', 'Bachelor in Elementary Education 1', '$2y$10$.OEeIO/Ejc89gMA8e3Xr8edzMAm.uOs6WhBX5Bjo1LsyWrFSL1tca', '[\"uploads/registration/regdoc_6a1a5c35f090e6.35129628.png\",\"uploads/registration/regdoc_6a1a5c35f1f349.72216048.png\"]', 1, NULL, NULL, 'approved', 1, '2026-05-30 11:43:17', '2026-05-30 11:40:57', '2026-05-30 11:43:17', 'student', NULL, 0, 'none', NULL),
(6, 'Kurumi Harvie Tokisaki', 'tokisaki2nikurumi@gmail.com', '23-02026', 'Bachelor of Science in Computer Science 4', '$2y$10$DstpprfaR8p0OTazd27/qeF0w6IxHQ7tBt4w5YG71iynOmdQ/M1/.', '[\"uploads\\/student_registrations\\/student_doc_6a5819a9853df3.17466314.jpg\",\"uploads\\/student_registrations\\/student_doc_6a5819a98598a1.32605412.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-07-16 07:44:37', '2026-07-16 07:39:06', '2026-07-16 07:44:37', 'student', NULL, 0, 'none', NULL),
(7, 'Aaaass Ddddd', 'yatogami1ichitohka@gmail.com', '22-36666', 'Bachelor of Science in Hospitality Management 2', '$2y$10$S7H7cIB1VUQy4QArYrZKYetHRnhUKkHkNy.51IGrJjgb1ZJtE0JJ2', '[\"uploads\\/student_registrations\\/student_doc_6a581b10bddbf6.01843592.jpg\",\"uploads\\/student_registrations\\/student_doc_6a581b10be4379.05756751.jpg\"]', 1, NULL, NULL, 'rejected', NULL, NULL, '2026-07-16 07:43:44', '2026-07-16 07:44:02', 'student', NULL, 0, 'none', NULL),
(8, 'Ggg Ddd Fgffdfd', 'nmiku43@gmail.com', '23-22663', 'Bachelor of Science in Hospitality Management 4', '$2y$10$WI93C4xFuMALsMghaAyI0urnW/pWXBxg9QMOpXZ23C6yop7SmwkQW', '[\"uploads\\/student_registrations\\/student_doc_6a5ea51c1a0d13.61295268.png\",\"uploads\\/student_registrations\\/student_doc_6a5ea51c1b1569.09344488.png\"]', 1, NULL, NULL, 'approved', 1, '2026-07-21 06:47:44', '2026-07-21 06:47:02', '2026-07-21 06:47:44', 'student', NULL, 0, 'none', NULL),
(9, 'Xzujien Raut-raut', 'elmarrautraut.bscs.pass@gmail.com', '23-02310', 'Bachelor of Science in Computer Science 4', '$2y$10$dwEZ2n4KaNgVDXUCxN3xpu8oSeHzWjxrPUjWw619btaH/2SHRkxPm', '[\"uploads\\/student_registrations\\/student_doc_6a72a467a9b645.87641954.png\",\"uploads\\/student_registrations\\/student_doc_6a72a467aa0fe8.02436818.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-08-05 10:52:38', '2026-08-05 10:51:14', '2026-08-05 10:52:38', 'student', NULL, 0, 'none', NULL),
(10, 'Leyharvie Barreto Peralta', 'leyharvieperalta15.bscs.pass@gmail.com', '24-02036', 'Bachelor of Science in Computer Science 4', '$2y$10$t3SnBLM3CZMi0SyGmh2/Veg4vbQiYWq7KRe8saugM/GMgrRLY1hPe', '[\"uploads\\/student_registrations\\/student_doc_6a9a306e969933.61030241.jpg\",\"uploads\\/student_registrations\\/student_doc_6a9a306e96e175.99108938.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-23 16:43:17', '2026-09-04 10:45:12', '2026-09-23 16:43:17', 'student', NULL, 0, 'none', NULL),
(11, 'Almirah Barreto Peralta', 'almirahperalta.bsa.pass@gmail.com', '26-03790', 'Bachelor of Science in Accountancy 1', '$2y$10$8ASxMvjc2fzg5Q64OiNyreHTCqzX5vXmmQVLyHwIKy1o.ikns7LRW', '[\"uploads\\/student_registrations\\/student_doc_6a9b721da1e128.66509650.jpg\",\"uploads\\/student_registrations\\/student_doc_6a9b721da29373.47635788.png\"]', 1, NULL, NULL, 'approved', 1, '2026-09-05 09:40:31', '2026-09-05 09:38:02', '2026-09-05 09:40:31', 'student', NULL, 0, 'none', NULL),
(12, 'Lee Mar Dizon Balan', 'leemarbalan.bsa.pass@gmail.com', '26-04121', 'Bachelor of Science in Accountancy 1', '$2y$10$cLpDT1KZpwpPKfbE4Phq9.gLPLQfZKJpKPQ5ldg.t5zuaj7zau7A.', '[\"uploads\\/student_registrations\\/student_doc_6aa2a23aefd7e8.11285920.jpg\",\"uploads\\/student_registrations\\/student_doc_6aa2a23af05fe4.39619871.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-10 20:35:04', '2026-09-10 20:28:22', '2026-09-10 20:35:04', 'student', NULL, 0, 'none', NULL),
(13, 'Amyed Dineros Cayabyab', 'amyedcayabyab.bsa.pass@gmail.com', '26-03935', 'Bachelor of Science in Accountancy 1', '$2y$10$CJ9DeOLwh42bcIT..0/KMOlNDjJuZGVdm/pt2Y6Ormb64iUgek6Cu', '[\"uploads\\/student_registrations\\/student_doc_6aa36a3c1a32d4.42827596.jpg\",\"uploads\\/student_registrations\\/student_doc_6aa36a3c1b5425.64739182.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-11 10:43:19', '2026-09-11 10:41:32', '2026-09-11 10:43:19', 'student', NULL, 0, 'none', NULL),
(14, 'Janine Untalasco', 'janineuntalasco.bsa.pass@gmail.com', '23-02199', 'Bachelor of Science in Accountancy 4', '$2y$10$9CS8rxp29VJVvZZHV1WAN.8mHV.WUO.rpxiY3eXpS1XABhGSzK73a', '[\"uploads\\/student_registrations\\/student_doc_6aa6006e2b6ae2.47998909.jpg\",\"uploads\\/student_registrations\\/student_doc_6aa6006e2d0368.89929476.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-13 09:54:58', '2026-09-13 09:46:58', '2026-09-13 09:54:58', 'student', NULL, 0, 'none', NULL),
(15, 'Jefford Apostol Anchet', 'jeffordapostolancheta17@gmail.com', '26-04011', 'Bachelor of Science in Accountancy 1', '$2y$10$ErPD0/A5pQ8YvMJHv6PM5Onqa/5CuaM3XcLKDOHAVmeugdC0/unb6', '[\"uploads\\/student_registrations\\/student_doc_6aaa8abebb21a5.48268600.png\",\"uploads\\/student_registrations\\/student_doc_6aaa8abebb8794.83079455.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-16 20:26:52', '2026-09-16 20:25:58', '2026-09-16 20:26:52', 'student', NULL, 0, 'none', NULL),
(16, 'Arianne Verzosa', 'arianneverzosa01@gmail.com', '26-04186', 'Bachelor of Science in Accountancy 1', '$2y$10$7c37/efS4LQ/jCXjtxZDqO3d5ZtbQzdK3WIlqzxPEttrVB3eeFiM6', '[\"uploads\\/student_registrations\\/student_doc_6aaa8be72e9783.27583362.jpg\",\"uploads\\/student_registrations\\/student_doc_6aaa8be72ef129.54799212.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-16 20:33:29', '2026-09-16 20:32:43', '2026-09-16 20:33:29', 'student', NULL, 0, 'none', NULL),
(17, 'Asad Asdasd Asda', 'jelexie3@gmail.com', NULL, 'Bachelor of Science in Computer Science', '$2y$10$snLNmE39YswY.Jy/jw1QTupthi/TkVTnX65PqNCbuolUeJv7vBu2e', '[]', 1, NULL, NULL, 'rejected', NULL, NULL, '2026-09-23 11:01:18', '2026-09-23 11:07:32', 'teacher', '123456', 0, 'none', NULL),
(18, 'Dsds Dssd Sds', 'izayoi7nanamiku@gmail.com', NULL, 'Bachelor of Science in Criminology', '$2y$10$MnK1fVwlL9T0NDttf2RxzeHU.3TNcO8nDRjRrJi1mzNSZmjSYtBYG', '[]', 1, NULL, NULL, 'rejected', NULL, NULL, '2026-09-23 11:07:03', '2026-09-23 11:07:28', 'teacher', '3333', 0, 'none', NULL),
(19, 'Adrian Cebu', 'adriancebu188@gmail.com', NULL, 'Bachelor of Science in Computer Science', '$2y$10$QKFreUA91pEN2jBhopHeyO1uG3z3P.5x/Rd6ls.goTPG9fk0k4EkS', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 11:13:48', '2026-09-23 11:13:01', '2026-09-23 11:13:48', 'teacher', '5201320', 1, 'none', NULL),
(20, 'John Lloyd Malano', 'malanojohnlloyd019@gmail.com', '23-02102', 'Bachelor of Science in Hospitality Management 4', '$2y$10$bSjkKnkf6lRVGFZZedCQuey.5XfcC1TFjbR7vXHOvxfW.DtMRvnni', '[\"uploads\\/student_registrations\\/student_doc_6ab39019b13116.86388941.jpg\",\"uploads\\/student_registrations\\/student_doc_6ab39019b19340.58934192.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-23 16:39:39', '2026-09-23 16:38:49', '2026-09-23 16:39:39', 'student', NULL, 0, 'none', NULL),
(21, 'Rodelyn Doctor', 'rodelyndoctor15@gmail.com', '23-02071', 'Bachelor of Science in Hospitality Management 4', '$2y$10$fSYXWrg5WoKRrHtwL1C/XOeKRVAqHm0kYdrnXhl.pA1bomN7k4oz6', '[\"uploads\\/student_registrations\\/student_doc_6ab39115566550.51199677.jpg\",\"uploads\\/student_registrations\\/student_doc_6ab3911556c137.86651644.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-23 16:54:50', '2026-09-23 16:43:01', '2026-09-23 16:54:50', 'student', NULL, 0, 'none', NULL),
(22, 'Natasha Mae Lantecsi', 'natashamaelantecsi.bshm.pass@gmail.com', '23-02340', 'Bachelor of Science in Hospitality Management 4', '$2y$10$/KjFi6y30BI8aaIFZtM32.I/dJHW11ZAbnHXLus65yTaeyfsDtLRq', '[\"uploads\\/student_registrations\\/student_doc_6ab391b82976d6.43435008.jpg\",\"uploads\\/student_registrations\\/student_doc_6ab391b829e250.36939314.jpg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-23 16:46:01', '2026-09-23 16:45:44', '2026-09-23 16:46:01', 'student', NULL, 0, 'none', NULL),
(23, 'Tricia Mae Tigno', 'triciamaetigno.bshm.pass@gmail.com', '23-02114', 'Bachelor of Science in Hospitality Management 4', '$2y$10$kvMyJ5/IDz3PUzvIybldQ..49V6Rth4UU1EM4.dNVnqvEErXDBatG', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 16:55:08', '2026-09-23 16:54:05', '2026-09-23 16:55:08', 'student', NULL, 0, 'none', NULL),
(24, 'Rose Patrice Hernal', 'rosepatricehernal.bshm.pass@gmail.com', '23-01999', 'Bachelor of Science in Hospitality Management 4', '$2y$10$UCPTUaJlA0I3EZATK.Zw3OAHry9/S.cS.8pCFTB.29yPzschpR1Ou', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:03:35', '2026-09-23 17:00:40', '2026-09-23 17:03:35', 'student', NULL, 0, 'none', NULL),
(25, 'Kristine Diaz', 'kristinediaz.bshm.pass@gmail.com', '23-02010', 'Bachelor of Science in Hospitality Management 4', '$2y$10$BJFdW9fq70ITjQU7CKy33OhCsN8h583fTxRmdY2rH0ByIuN0LimXm', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:11', '2026-09-23 17:00:40', '2026-09-23 17:04:11', 'student', NULL, 0, 'none', NULL),
(26, 'Eljhee Mae Enate', 'eljheemaeg.enatebshm.pass@gmail.com', '23-02130', 'Bachelor of Science in Hospitality Management 4', '$2y$10$yl2/yTGXAXUKmuGTDETrUOFPcawvn/4EoHFpzIfpsYNBNhS5OwOU.', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:23', '2026-09-23 17:00:40', '2026-09-23 17:04:23', 'student', NULL, 0, 'none', NULL),
(27, 'Shaile Anne Espinoza', 'shaileanneespinoza.bshm.pass@gmail.com', '23-02103', 'Bachelor of Science in Hospitality Management 4', '$2y$10$rmyDJp4PzsJCrJpdeemRj.OEyp1bE94uUw8nXkA61Go.Xcsb1XKRm', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:28', '2026-09-23 17:00:40', '2026-09-23 17:04:28', 'student', NULL, 0, 'none', NULL),
(28, 'Wengel Divinagracia', 'wengeldivinagracia.bshm.pass@gmail.com', '23-02201', 'Bachelor of Science in Hospitality Management 4', '$2y$10$Zma2zNBqDkFQPK/aoPHb4e/0pDFxLcYOKS2oi95i2e71EtIshGEfe', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:33', '2026-09-23 17:00:41', '2026-09-23 17:04:33', 'student', NULL, 0, 'none', NULL),
(29, 'Carl Jhustine Borbon', 'borboncarljhustine@gmail.com', '23-02155', 'Bachelor of Science in Hospitality Management 4', '$2y$10$unN1a48Dq751D6MXuJyUMOuIoV39CUCLg7gTN81MxbiDM0TBJGmDC', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:37', '2026-09-23 17:00:41', '2026-09-23 17:04:37', 'student', NULL, 0, 'none', NULL),
(30, 'Raquel Macadenden', 'macadendenraquel8@gmail.com', '24-02546', 'Bachelor of Science in Hospitality Management 4', '$2y$10$GROEOtdExZDlW9fg6MOwBe5DhHh70WfQnJe0Gacf.xDQzOhv427XW', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:42', '2026-09-23 17:00:41', '2026-09-23 17:04:42', 'student', NULL, 0, 'none', NULL),
(31, 'Rica Mae Pobletin', 'pobletinricamae@gmail.com', '23-01237', 'Bachelor of Science in Hospitality Management 4', '$2y$10$dtV.1VZsHnWtOzinJc67x.gaUmB5xQFdAYFw3NEO1byJGuv1fnCSC', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:46', '2026-09-23 17:00:41', '2026-09-23 17:04:46', 'student', NULL, 0, 'none', NULL),
(32, 'Justine Quiliza', 'justinequiliza.bshm1.pass@gmail.com', '23-02384', 'Bachelor of Science in Hospitality Management 4', '$2y$10$BO0MwodvP1340q4jBxS91ODJ9hKxYxuTMH2lNpiE5enZ0ZT5md.ee', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:51', '2026-09-23 17:00:41', '2026-09-23 17:04:51', 'student', NULL, 0, 'none', NULL),
(33, 'Maria Maricolle Jubilado', 'mariamaricollejubilado.bshm.pass@gmail.com', '23-01985', 'Bachelor of Science in Hospitality Management 4', '$2y$10$j38BgA/6u/gtxWtx.Zcsmudq.Xd5vTaQmMVnMrzklOgLwofV6uuzW', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:04:55', '2026-09-23 17:00:42', '2026-09-23 17:04:55', 'student', NULL, 0, 'none', NULL),
(34, 'Jeniefer Obice', 'obicejenieferd@gmail.com', '23-02259', 'Bachelor of Science in Hospitality Management 4', '$2y$10$iNpp0wSguXKlNlgX2PHzZerpkzRDXI.OvRhDcgN28ttzoRUxlvrLu', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:05:01', '2026-09-23 17:00:42', '2026-09-23 17:05:01', 'student', NULL, 0, 'none', NULL),
(35, 'John Christian Camero', 'camerojohnchristian@gmail.com', '23-02319', 'Bachelor of Science in Hospitality Management 4', '$2y$10$fz4ynGGOLc3CMmewUXwmC.Zxft5Hry0TNlx5zl4jtFh2hjJK0bgI2', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:05:05', '2026-09-23 17:00:42', '2026-09-23 17:05:05', 'student', NULL, 0, 'none', NULL),
(36, 'John Almartin Elorde', 'johnalmartinelorde@gmail.com', '23-02204', 'Bachelor of Science in Hospitality Management 4', '$2y$10$CdbsDzzvEW7izfWTGMqBGept4GdpJ930LOxOe89maFdjAavSf8MNi', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:05:11', '2026-09-23 17:00:42', '2026-09-23 17:05:11', 'student', NULL, 0, 'none', NULL),
(37, 'Michael Angelo Cacho', 'angelotorrescacho@gmail.com', '23-02165', 'Bachelor of Science in Hospitality Management 4', '$2y$10$ViyZ8rvk4tSzXvmeVZTPtenyNOe0j6azZ6gT0Zpm0/I6dsAzS5W2e', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:05:15', '2026-09-23 17:00:42', '2026-09-23 17:05:15', 'student', NULL, 0, 'none', NULL),
(38, 'Alicia Nikolle Orfinada', 'alicianikolle29@gmail.com', '23-02232', 'Bachelor of Science in Hospitality Management 4', '$2y$10$56/LDJjCpWn4pu2.L3yUh.lehVFmBhT/QQNsp73gRV/2ZRiJLZcJu', '[]', 1, NULL, NULL, 'approved', 1, '2026-09-23 17:05:20', '2026-09-23 17:00:43', '2026-09-23 17:05:20', 'student', NULL, 0, 'none', NULL),
(39, 'Mary Joy Mercado Areniego', 'maryjoyareniego0@gmail.com', '23-02014', 'Bachelor of Science in Hospitality Management 4', '$2y$10$PkwuXACnkKgahRNcThIKDOxDk6yZhF2uJY7wqL502Gl4yb6H9bR8i', '[\"uploads\\/student_registrations\\/student_doc_6ab3c4cc149b18.11998519.jpeg\",\"uploads\\/student_registrations\\/student_doc_6ab3c4cc1531e2.16081616.jpeg\"]', 1, NULL, NULL, 'approved', 1, '2026-09-23 20:33:00', '2026-09-23 20:23:40', '2026-09-23 20:33:00', 'student', NULL, 0, 'none', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_announcements`
--

CREATE TABLE `scholarship_announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `program_type` enum('TDP','TES','financial_aid','other') DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_announcement_images`
--

CREATE TABLE `scholarship_announcement_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_applications`
--

CREATE TABLE `scholarship_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `status` enum('ready','pending','approved','rejected') NOT NULL DEFAULT 'ready',
  `comments` text DEFAULT NULL,
  `ready_timestamp` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_document_validations`
--

CREATE TABLE `scholarship_document_validations` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `validated_by` int(10) UNSIGNED NOT NULL,
  `validated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`requirements`)),
  `notes` text DEFAULT NULL,
  `validation_date` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_updates`
--

CREATE TABLE `scholarship_updates` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `update_type` enum('deadline','program_info','financial_assistance','general') NOT NULL DEFAULT 'general',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_validation_feedback`
--

CREATE TABLE `scholarship_validation_feedback` (
  `id` int(11) UNSIGNED NOT NULL,
  `validation_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `process_rating` tinyint(4) DEFAULT 0,
  `support_rating` tinyint(4) DEFAULT 0,
  `review_text` text DEFAULT NULL,
  `feedback_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_alumni`
--

CREATE TABLE `ssaa_alumni` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Original user ID before graduation',
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `course` varchar(100) NOT NULL,
  `graduation_year` year(4) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `employment_status` enum('employed','unemployed','unknown') NOT NULL DEFAULT 'unknown',
  `company_name` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `industry` varchar(100) DEFAULT NULL,
  `job_level` varchar(50) DEFAULT NULL,
  `salary_range` varchar(50) DEFAULT NULL,
  `employment_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `quote` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `ladderized_course` varchar(10) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `civil_status` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `personal_email` varchar(150) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `congratulations_shown` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssaa_alumni`
--

INSERT INTO `ssaa_alumni` (`id`, `user_id`, `full_name`, `email`, `student_id`, `course`, `graduation_year`, `contact_number`, `employment_status`, `company_name`, `job_title`, `last_updated`, `created_at`, `industry`, `job_level`, `salary_range`, `employment_date`, `location`, `job_description`, `achievements`, `quote`, `profile_image`, `ladderized_course`, `gender`, `civil_status`, `dob`, `permanent_address`, `personal_email`, `nationality`, `congratulations_shown`, `is_archived`, `archived_at`, `archived_by`) VALUES
(2, NULL, 'lakshal', 'aa@gmail.com', '0995024623.30', 'Bachelor of Science in Computer Science', '2004', '09999', 'employed', 'dr', 'dont know', '2026-09-24 08:16:13', '2026-04-11 09:47:01', 'N/A', 'Entry Level', 'N/A', NULL, 'N/A', 'N/A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(3, 11, 'ako ay walang lobo', 'lakshal369@gmail.com', '232121212', 'Bachelor of Science in Hospitality Management 4', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-04-14 16:17:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(4, 36, 'Ley Harvie Barreto Peralta', 'leyharviep@gmail.com', '23-02036', 'Bachelor of Science in Computer Science', '2026', '0966664646649', 'employed', 'Ako', NULL, '2026-09-24 08:16:59', '2026-04-14 16:17:30', 'Hospitality', NULL, 'Under ₱15,000', NULL, 'Dont know', 'Yes', NULL, NULL, NULL, NULL, 'Male', 'Single', '2005-06-15', 'Diko alam', 'leyharviep@gmail.com', 'Filipino', 0, 0, NULL, NULL),
(6, 33, 'Suto Iku', 'kyuu9sutoiku@gmail.com', '11-11111', 'Bachelor of Science in Business Administration Graduate', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-05-17 09:28:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(7, 15, 'Har Vie', 'fernand1fable@gmail.com', '23-02044', 'Bachelor of Science in Hospitality Management 4', '2026', NULL, 'employed', 'dd', 'dont know', '2026-09-24 08:16:13', '2026-05-17 09:42:17', 'Healthcare', 'Junior', 'Under ₱15,000', '2026-05-11', 'ghghgh', 'gggggggggggg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(8, 17, 'Har Dd Dd', 'kusurigoyakuzen@gmail.com', '22-22564', 'Bachelor of Science in Accountancy 4', '2026', NULL, 'unknown', NULL, NULL, '2026-08-15 09:24:55', '2026-05-17 09:54:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-08-15 09:24:55', 1),
(9, NULL, 'aaa', 'awa@gmail.com', '3333', 'Associate in Computer Technology', '2000', '099994444444444', 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-05-17 11:17:26', NULL, NULL, NULL, NULL, NULL, NULL, 'Cum Laude, president', 'to be a amama', 'IMG ASSETS/alumni_profiles/alumni_6a0933469c5ea9.21562298.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(10, NULL, 'a', 'awaa@gmail.com', '0995024623.30w', 'Bachelor of Science in Hospitality Management', '2000', '099994544', 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-05-17 11:47:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(12, 58, 'Mm Mmmh Hmhmh', 'juugo15momimomiji@gmail.com', '23-44444', 'Bachelor of Science in Computer Science 4', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-06-20 09:15:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(13, 59, 'Aa Ss Aa', 'juurok16yakuzenyaku@gmail.com', '22-22222', 'Bachelor of Science in Accountancy 4', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-06-20 09:21:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(14, 60, 'Daddd Adad Dada', 'juunana17torotorokishika@gmail.com', '33-33333', 'Bachelor of Science in Criminology 4', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-06-20 09:35:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(15, 47, 'Elmar Raut-raut', 'marrautraut@gmail.com', '23-02363', 'Bachelor of Science in Computer Science', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:24:22', '2026-06-20 10:16:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '../IMG ASSETS/alumni_profiles/alumni_6ab46db6653fd4.80907589.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL),
(16, 61, 'Xxx Xxd Dxxx', 'juuachi18aashiikedarui@gmail.com', '23-44434', 'Associate in Business Knowledge 2', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-06-20 10:16:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(17, 62, 'Onhkih Hhi Hig', 'juukyuu19utonakaji@gmail.com', '65-66666', 'Bachelor of Science in Business Administration 4', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-06-20 10:37:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(18, 63, 'Efsdfsd Ered Sadfs', 'nijuu20maimeido@gmail.com', '22-22343', 'Bachelor of Science in Criminology 4', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-06-20 10:58:48', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1),
(19, 46, 'Jasmin Arachea', 'aracheajasmin@gmail.com', '23-02320', 'Bachelor of Science in Computer Science', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:25:14', '2026-06-20 11:09:39', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL),
(20, 65, 'Kurumi Harvie Tokisaki', 'tokisaki2nikurumi@gmail.com', '23-02026', 'Bachelor of Science in Computer Science 4', '2026', NULL, 'unknown', NULL, NULL, '2026-09-24 08:16:13', '2026-07-27 09:55:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-09-24 08:16:13', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_alumni_batch_emails`
--

CREATE TABLE `ssaa_alumni_batch_emails` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_year` year(4) NOT NULL,
  `sent_by_user_id` int(10) UNSIGNED NOT NULL,
  `alumni_count` int(11) NOT NULL DEFAULT 0,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `failed_count` int(11) NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `email_status` enum('pending','sending','completed','failed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssaa_alumni_batch_emails`
--

INSERT INTO `ssaa_alumni_batch_emails` (`id`, `batch_year`, `sent_by_user_id`, `alumni_count`, `sent_count`, `failed_count`, `sent_at`, `email_status`, `created_at`) VALUES
(1, '2026', 0, 1, 1, 0, '2026-06-20 08:32:32', 'completed', '2026-06-20 08:32:32'),
(2, '2026', 0, 1, 1, 0, '2026-06-20 08:39:16', 'completed', '2026-06-20 08:39:16'),
(3, '2026', 0, 1, 1, 0, '2026-06-20 12:15:32', 'completed', '2026-06-20 12:15:32'),
(4, '2026', 0, 1, 1, 0, '2026-06-20 12:24:39', 'completed', '2026-06-20 12:24:39'),
(5, '2026', 0, 1, 1, 0, '2026-06-20 12:36:11', 'completed', '2026-06-20 12:36:11'),
(6, '2026', 0, 1, 1, 0, '2026-06-20 12:54:07', 'completed', '2026-06-20 12:54:07'),
(7, '2026', 0, 1, 1, 0, '2026-09-21 14:04:57', 'completed', '2026-09-21 14:04:57');

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_alumni_career_milestones`
--

CREATE TABLE `ssaa_alumni_career_milestones` (
  `id` int(10) UNSIGNED NOT NULL,
  `alumni_id` int(10) UNSIGNED NOT NULL,
  `milestone_type` varchar(100) NOT NULL,
  `title` varchar(150) NOT NULL,
  `milestone_date` date NOT NULL,
  `company` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssaa_alumni_career_milestones`
--

INSERT INTO `ssaa_alumni_career_milestones` (`id`, `alumni_id`, `milestone_type`, `title`, `milestone_date`, `company`, `description`, `created_at`) VALUES
(1, 2, 'Education', 'ff', '2026-04-19', 'dd', NULL, '2026-04-11 10:36:18'),
(2, 2, 'Education', 'rrr', '2026-04-15', 'rrrr', 'rrr', '2026-04-11 10:37:03');

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_alumni_survey_questions`
--

CREATE TABLE `ssaa_alumni_survey_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `question_type` enum('text','choice') NOT NULL DEFAULT 'text',
  `employment_status` enum('all','employed','self-employed','unemployed') NOT NULL DEFAULT 'all',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_removable` tinyint(1) NOT NULL DEFAULT 1,
  `field_name` varchar(50) DEFAULT NULL,
  `answer_choices` longtext DEFAULT NULL COMMENT 'JSON array of choices',
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_alumni_survey_responses`
--

CREATE TABLE `ssaa_alumni_survey_responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `alumni_id` int(10) UNSIGNED NOT NULL,
  `response_data` longtext NOT NULL COMMENT 'JSON object with all responses',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssaa_alumni_survey_responses`
--

INSERT INTO `ssaa_alumni_survey_responses` (`id`, `alumni_id`, `response_data`, `created_at`, `updated_at`) VALUES
(1, 4, '{\"full_name\":\"ley harvie peralta\",\"student_id\":\"23-02036\",\"graduation_year\":\"2026\",\"course\":\"Bachelor of Science in Computer Science\",\"gender\":\"Male\",\"civil_status\":\"Single\",\"date_of_birth\":\"2005-06-15\",\"nationality\":\"filipino\",\"personal_email\":\"leyharviep@gmail.com\",\"mobile_number\":\"0966664646649\",\"address\":\"Diko alam\",\"employment_status\":\"Employed\",\"job_description\":\"Yes\",\"company_title\":\"Ako\",\"employment_location\":\"Dont know\",\"industry\":\"Hospitality\",\"salary_range\":\"Under \\u20b115,000\",\"self_employed_description\":\"\",\"self_employed_industry\":\"\",\"self_employed_location\":\"\",\"self_employed_income\":\"\",\"unemployment_reason\":\"\",\"unemployment_comments\":\"\"}', '2026-06-20 12:55:54', '2026-06-20 12:55:54');

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_audit_trail`
--

CREATE TABLE `ssaa_audit_trail` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `action_type` enum('promotion','graduation','employment_update','bulk_operation','status_change') NOT NULL,
  `entity_type` enum('student','alumni','graduation_event') NOT NULL,
  `entity_id` int(10) UNSIGNED NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssaa_audit_trail`
--

INSERT INTO `ssaa_audit_trail` (`id`, `admin_id`, `action_type`, `entity_type`, `entity_id`, `old_value`, `new_value`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'promotion', 'student', 7, NULL, NULL, 'Test promotion', NULL, NULL, '2026-04-08 10:54:48'),
(2, 1, 'promotion', 'student', 11, NULL, NULL, 'Bulk promotion to next level', NULL, NULL, '2026-04-08 10:55:40'),
(3, 1, '', 'student', 13, NULL, NULL, 'Ready for graduation from 4-year program', NULL, NULL, '2026-04-10 17:28:39'),
(4, 1, '', 'student', 14, NULL, NULL, 'Ready for graduation from 4-year program', NULL, NULL, '2026-04-12 13:38:07'),
(5, 1, 'graduation', 'student', 33, NULL, NULL, 'Ready for graduation from 4-year program', NULL, NULL, '2026-05-17 09:28:10'),
(6, 1, 'promotion', 'student', 15, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-05-17 09:41:20'),
(7, 1, 'graduation', 'student', 15, NULL, NULL, 'Ready for graduation from 4-year program', NULL, NULL, '2026-05-17 09:42:16'),
(8, 1, 'promotion', 'student', 17, NULL, NULL, 'Advanced to 3 Year', NULL, NULL, '2026-05-17 09:53:51'),
(9, 1, 'promotion', 'student', 17, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-05-17 09:53:58'),
(10, 1, 'graduation', 'student', 17, NULL, NULL, 'Ready for graduation from 4-year program', NULL, NULL, '2026-05-17 09:54:10'),
(11, 1, 'promotion', 'student', 36, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-06-19 16:34:27'),
(12, 1, 'graduation', 'student', 36, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-19 17:00:11'),
(13, 1, 'graduation', 'student', 58, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 09:15:24'),
(14, 1, 'promotion', 'student', 47, NULL, NULL, 'Advanced to 2 Year', NULL, NULL, '2026-06-20 09:19:03'),
(15, 1, 'promotion', 'student', 47, NULL, NULL, 'Advanced to 3 Year', NULL, NULL, '2026-06-20 09:19:09'),
(16, 1, 'promotion', 'student', 47, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-06-20 09:19:17'),
(17, 1, 'promotion', 'student', 59, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-06-20 09:21:46'),
(18, 1, 'graduation', 'student', 59, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 09:21:57'),
(19, 1, 'promotion', 'student', 60, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-06-20 09:34:30'),
(20, 1, 'graduation', 'student', 60, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 09:34:46'),
(21, 1, 'graduation', 'student', 47, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 09:54:10'),
(22, 1, 'graduation', 'student', 61, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 10:14:39'),
(23, 1, 'promotion', 'student', 62, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-06-20 10:36:38'),
(24, 1, 'graduation', 'student', 62, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 10:36:44'),
(25, 1, 'promotion', 'student', 63, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-06-20 10:57:51'),
(26, 1, 'graduation', 'student', 63, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 10:57:57'),
(27, 1, 'promotion', 'student', 46, NULL, NULL, 'Advanced to 4 Year', NULL, NULL, '2026-06-20 11:07:48'),
(28, 1, 'graduation', 'student', 46, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-06-20 11:07:54'),
(29, 1, 'graduation', 'student', 65, NULL, NULL, 'Marked as graduating via bulk Process Graduation', NULL, NULL, '2026-07-27 09:55:11');

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_feedback`
--

CREATE TABLE `ssaa_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL,
  `service_quality` int(11) DEFAULT NULL CHECK (`service_quality` >= 1 and `service_quality` <= 5),
  `staff_courtesy` int(11) DEFAULT NULL CHECK (`staff_courtesy` >= 1 and `staff_courtesy` <= 5),
  `process_efficiency` int(11) DEFAULT NULL CHECK (`process_efficiency` >= 1 and `process_efficiency` <= 5),
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `moderated_by` int(10) UNSIGNED DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ssaa_feedback`
--

INSERT INTO `ssaa_feedback` (`id`, `user_id`, `rating`, `comments`, `service_quality`, `staff_courtesy`, `process_efficiency`, `is_approved`, `moderated_by`, `moderated_at`, `created_at`) VALUES
(1, 36, 5, 'asazsasas', NULL, NULL, NULL, 1, NULL, NULL, '2026-06-06 15:42:34');

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_graduation_events`
--

CREATE TABLE `ssaa_graduation_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `graduates_count` int(11) DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ssaa_student_progression`
--

CREATE TABLE `ssaa_student_progression` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(20) NOT NULL COMMENT 'e.g., BSCS 2, ICT 3',
  `academic_status` enum('passed','failed','irregular','pending') NOT NULL DEFAULT 'pending',
  `enrollment_status` enum('active','transferred','discontinued','graduating','graduated') NOT NULL DEFAULT 'active',
  `current_semester` enum('1st','2nd') DEFAULT '1st',
  `transfer_date` date DEFAULT NULL,
  `transfer_reason` text DEFAULT NULL,
  `discontinuation_date` date DEFAULT NULL,
  `discontinuation_reason` text DEFAULT NULL,
  `graduation_date` date DEFAULT NULL,
  `last_semester_gpa` decimal(3,2) DEFAULT NULL,
  `promotion_eligible` tinyint(1) NOT NULL DEFAULT 1,
  `gpa` decimal(3,2) DEFAULT NULL,
  `failed_subjects` int(11) DEFAULT 0,
  `total_subjects` int(11) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `evaluated_by` int(10) UNSIGNED DEFAULT NULL,
  `evaluated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssaa_student_progression`
--

INSERT INTO `ssaa_student_progression` (`id`, `user_id`, `academic_year`, `academic_status`, `enrollment_status`, `current_semester`, `transfer_date`, `transfer_reason`, `discontinuation_date`, `discontinuation_reason`, `graduation_date`, `last_semester_gpa`, `promotion_eligible`, `gpa`, `failed_subjects`, `total_subjects`, `remarks`, `evaluated_by`, `evaluated_at`, `created_at`, `updated_at`) VALUES
(1, 11, '', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-04-14', NULL, 1, NULL, 0, 0, NULL, 1, '2026-04-08 10:51:30', '2026-04-08 10:51:30', '2026-04-14 16:17:30'),
(6, 13, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-04-14', NULL, 1, NULL, 0, 0, NULL, 1, '2026-04-10 17:28:39', '2026-04-10 17:28:39', '2026-04-14 16:17:30'),
(7, 14, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-04-14', NULL, 1, NULL, 0, 0, NULL, 1, '2026-04-12 13:38:07', '2026-04-12 13:38:07', '2026-04-14 16:17:30'),
(8, 33, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-05-17', NULL, 1, NULL, 0, 0, NULL, 1, '2026-05-17 09:28:10', '2026-05-17 09:28:10', '2026-05-17 09:28:11'),
(9, 15, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-05-17', NULL, 1, NULL, 0, 0, NULL, 1, '2026-05-17 09:42:16', '2026-05-17 09:41:20', '2026-05-17 09:42:17'),
(11, 17, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-05-17', NULL, 1, NULL, 0, 0, NULL, 1, '2026-05-17 09:54:10', '2026-05-17 09:53:51', '2026-05-17 09:54:12'),
(14, 36, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-19', NULL, 1, NULL, 0, 0, NULL, 1, '2026-06-19 16:34:27', '2026-06-19 16:34:27', '2026-06-19 18:00:01'),
(15, 58, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, NULL, NULL, '2026-06-20 09:15:24', '2026-06-20 09:15:25'),
(16, 47, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, 1, '2026-06-20 09:19:17', '2026-06-20 09:19:03', '2026-06-20 10:16:09'),
(19, 59, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, 1, '2026-06-20 09:21:46', '2026-06-20 09:21:46', '2026-06-20 09:21:58'),
(20, 60, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, 1, '2026-06-20 09:34:30', '2026-06-20 09:34:30', '2026-06-20 09:35:33'),
(21, 61, 'Associate in Busines', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, NULL, NULL, '2026-06-20 10:14:39', '2026-06-20 10:16:09'),
(22, 62, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, 1, '2026-06-20 10:36:38', '2026-06-20 10:36:38', '2026-06-20 10:37:16'),
(23, 63, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, 1, '2026-06-20 10:57:51', '2026-06-20 10:57:51', '2026-06-20 10:58:48'),
(24, 46, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-06-20', NULL, 1, NULL, 0, 0, NULL, 1, '2026-06-20 11:07:48', '2026-06-20 11:07:48', '2026-06-20 11:09:39'),
(25, 65, 'Bachelor of Science ', 'passed', 'graduated', '1st', NULL, NULL, NULL, NULL, '2026-07-27', NULL, 1, NULL, 0, 0, NULL, NULL, NULL, '2026-07-27 09:55:11', '2026-07-27 09:55:15');

-- --------------------------------------------------------

--
-- Table structure for table `ssc_candidates`
--

CREATE TABLE `ssc_candidates` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `position` varchar(100) NOT NULL,
  `course_year` varchar(100) DEFAULT NULL,
  `platform` text DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `status` enum('active','withdrawn','elected','previous','advicer') NOT NULL DEFAULT 'active',
  `officer_year` varchar(20) DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssc_candidates`
--

INSERT INTO `ssc_candidates` (`id`, `full_name`, `position`, `course_year`, `platform`, `photo_url`, `status`, `officer_year`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 's', 's', 'Bachelor of Science in Tourism Management 3', 's', NULL, '', NULL, 10, '2026-04-04 14:54:53', '2026-04-04 15:00:11'),
(6, 'eee', 'eee', 'Bachelor of Science in Accountancy 2', 'eee', NULL, '', NULL, 10, '2026-04-04 15:00:11', '2026-04-04 15:03:36'),
(12, 'Balire E. Dela Cerna', 'College Representatives', 'Bachelor in Elementary Education', '', NULL, 'elected', NULL, 10, '2026-05-19 16:55:32', '2026-05-19 16:55:32'),
(13, 'Vania Alysa B. Oliva', 'College Representatives', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-19 16:56:03', '2026-05-19 16:56:03'),
(14, 'Richyl Marie Cruz', 'College Representatives', 'Bachelor of Science in Criminology', '', NULL, 'elected', NULL, 10, '2026-05-19 16:56:21', '2026-05-19 16:56:21'),
(15, 'Jerome Rudolph B. Prado', 'College Representatives', 'Bachelor of Science in Computer Science', '', NULL, 'elected', NULL, 10, '2026-05-19 16:56:44', '2026-05-19 16:56:44'),
(16, 'Diana Rose De Guzman', 'College Representatives', 'Bachelor of Science in Tourism Management', '', NULL, 'elected', NULL, 10, '2026-05-19 16:57:25', '2026-05-19 16:57:25'),
(17, ' Jarrell Kent C. Navida', 'College Representatives', 'Bachelor of Science in Hospitality Management', '', NULL, 'elected', NULL, 10, '2026-05-19 16:57:46', '2026-05-19 16:57:46'),
(18, 'Dian Fernandez', 'College Representatives', 'Bachelor of Science in Hospitality Management', '', NULL, 'elected', NULL, 10, '2026-05-19 16:58:02', '2026-05-19 16:58:02'),
(19, 'Christine Joyce Ramos', 'College Representatives', 'Bachelor of Science in Hospitality Management', '', NULL, 'elected', NULL, 10, '2026-05-19 16:58:15', '2026-05-19 16:58:15'),
(20, 'Lovely Ann Orquiza', 'College Representatives', 'Bachelor of Science in Hospitality Management', '', NULL, 'elected', NULL, 10, '2026-05-19 16:58:31', '2026-05-19 16:58:31'),
(21, 'John Ivan Ariston Vales', 'President', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-20 08:25:33', '2026-05-20 08:25:33'),
(22, 'Glaicel Mae Catalan', 'Vice President for Academic Affairs', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-20 08:27:22', '2026-05-20 08:27:22'),
(23, 'John Christian C. Camero', 'Vice President for Non-Academic Affairs', 'Bachelor of Science in Hospitality Management', '', NULL, 'elected', NULL, 10, '2026-05-20 08:29:05', '2026-05-20 08:29:05'),
(24, 'Louie John Bacolcol', 'Vice President for Finance', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-20 08:29:38', '2026-05-20 08:29:38'),
(25, 'Angelie Catabay', 'Deputy for Finance', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-20 08:31:13', '2026-05-20 08:31:13'),
(26, 'Jeraldine B. Jimenez', 'Vice President for Audit', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-20 08:32:14', '2026-05-20 08:32:14'),
(27, 'Renniel T. Aquino', 'Deputy Vice Auditor', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-20 08:32:58', '2026-05-20 08:32:58'),
(28, 'Jeremy N. Tugas ', 'Vice President for Public Relations', 'Bachelor of Science in Tourism Management', '', NULL, 'elected', NULL, 10, '2026-05-20 08:34:09', '2026-05-20 08:34:09'),
(29, 'John Reyvic Franco', 'Vice President for Marketing & Operations', 'Bachelor of Science in Business Administration', '', NULL, 'elected', NULL, 10, '2026-05-20 08:34:55', '2026-05-20 08:34:55'),
(30, 'Sarah Miles D. Orsoa', 'Vice President for Marketing & Operations ', 'Bachelor of Science in Accountancy', '', NULL, 'elected', NULL, 10, '2026-05-20 08:36:06', '2026-05-20 08:36:06'),
(31, 'Elmar Raut-raut', 'Media and Publications Officers', 'Bachelor of Science in Computer Science', '', NULL, 'elected', NULL, 10, '2026-05-20 08:36:33', '2026-05-20 08:36:33'),
(32, 'Niel John P. Alvez', 'Media and Publications Officers', 'Bachelor of Science in Computer Science', '', NULL, 'elected', NULL, 10, '2026-05-20 08:37:33', '2026-05-20 08:37:33'),
(33, 'ohncelle Khent P. Bagorio', 'Media and Publications Officers', 'Bachelor of Science in Computer Science', '', NULL, 'elected', NULL, 10, '2026-05-20 08:37:54', '2026-05-20 08:37:54'),
(34, 'Danielle Olivo', 'Executive Secretary', 'Bachelor in Elementary Education', '', NULL, 'elected', NULL, 10, '2026-05-20 08:38:36', '2026-05-20 08:38:36'),
(35, 'Irish Nicole R. Pacayra', 'Deputy Secretary', 'Bachelor of Science in Business Administration', '', NULL, 'elected', NULL, 10, '2026-05-20 09:05:17', '2026-05-20 09:05:17'),
(36, 'Elisabeth O. Mendez', 'SSC Advicer', '', '', NULL, '', '2025-2026', 10, '2026-06-08 11:25:59', '2026-06-08 11:25:59'),
(39, 'Elisabeth O. Mendez', 'SSC Advicer', '', '', NULL, '', '2025-2026', 10, '2026-06-08 11:31:03', '2026-06-08 11:31:03'),
(41, 'Elisabeth O. Mendez', 'SSC Advicer', '', '', NULL, '', '2025-2026', 10, '2026-06-08 11:46:38', '2026-06-08 11:46:38'),
(42, 'Elisabeth O. Mendez', 'SSC Advicer', '', '', NULL, '', '2025-2026', 10, '2026-06-08 11:52:45', '2026-06-08 11:52:45'),
(43, 'Elisabeth O. Mendez', 'SSC Advicer', '', '', NULL, '', '2025-2026', 10, '2026-06-08 11:52:58', '2026-06-08 11:52:58'),
(44, 'Elisabeth O. Mendez', 'SSC Advicer', '', '', NULL, 'advicer', '2025-2026', 10, '2026-06-08 11:56:44', '2026-06-08 11:56:44');

-- --------------------------------------------------------

--
-- Table structure for table `ssc_events`
--

CREATE TABLE `ssc_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `organizer` varchar(150) DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `event_type` varchar(50) DEFAULT 'event'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssc_events`
--

INSERT INTO `ssc_events` (`id`, `title`, `description`, `event_date`, `event_time`, `location`, `organizer`, `photo_url`, `status`, `created_by`, `created_at`, `updated_at`, `event_type`) VALUES
(13, 'WALANGPASOK', '29 AUGUST 2026 | SATURDAY\r\nIn line with the announcement of the LGU–Alaminos City, Pangasinan regarding the suspension of classes due to the continuing heavy rainfall brought by the Southwest Monsoon (Habagat), please be informed that both face-to-face and online classes are hereby suspended.\r\nDespite the class suspension, the Registrar and Accounting Offices shall remain open and available to assist students with school-related inquiries and concerns.\r\nPlease be guided accordingly. \r\nStay safe, PASSians! 🩵', '2026-09-05', NULL, '', '', NULL, 'upcoming', 10, '2026-09-05 15:44:51', '2026-09-05 15:44:51', 'others');

-- --------------------------------------------------------

--
-- Table structure for table `ssc_event_photos`
--

CREATE TABLE `ssc_event_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ssc_event_photos`
--

INSERT INTO `ssc_event_photos` (`id`, `event_id`, `photo_url`, `created_at`) VALUES
(29, 13, 'IMG ASSETS/ssc_events/ssc_event_6a9bc8736c5078.39125683.jpg', '2026-09-05 15:44:51');

-- --------------------------------------------------------

--
-- Table structure for table `ssc_feedback`
--

CREATE TABLE `ssc_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL,
  `service_quality` int(11) DEFAULT NULL CHECK (`service_quality` >= 1 and `service_quality` <= 5),
  `event_organization` int(11) DEFAULT NULL CHECK (`event_organization` >= 1 and `event_organization` <= 5),
  `overall_satisfaction` int(11) DEFAULT NULL CHECK (`overall_satisfaction` >= 1 and `overall_satisfaction` <= 5),
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `moderated_by` int(10) UNSIGNED DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ssc_feedback`
--

INSERT INTO `ssc_feedback` (`id`, `user_id`, `event_id`, `rating`, `comments`, `service_quality`, `event_organization`, `overall_satisfaction`, `is_approved`, `moderated_by`, `moderated_at`, `created_at`) VALUES
(1, 36, NULL, 5, 'fff', NULL, NULL, NULL, 1, NULL, NULL, '2026-06-06 14:57:21');

-- --------------------------------------------------------

--
-- Table structure for table `ssc_initiatives`
--

CREATE TABLE `ssc_initiatives` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('planning','active','completed','cancelled') NOT NULL DEFAULT 'planning',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `participants_count` int(11) DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ssc_officers`
--

CREATE TABLE `ssc_officers` (
  `id` int(10) UNSIGNED NOT NULL,
  `position` varchar(150) NOT NULL,
  `officer_name` varchar(150) NOT NULL,
  `order_sequence` int(11) DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role` enum('student','teacher','admin') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `course_year` varchar(60) DEFAULT NULL,
  `enrollment_status` enum('active','inactive','transferred','graduated','discontinued') NOT NULL DEFAULT 'active',
  `account_deactivation_date` date DEFAULT NULL,
  `deactivation_reason` text DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `is_head` tinyint(1) NOT NULL DEFAULT 0,
  `head_service` enum('none','library','clinic','ssc_scholarship','guidance') NOT NULL DEFAULT 'none',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `admin_type` enum('master','regular') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `username`, `role`, `is_active`, `course_year`, `enrollment_status`, `account_deactivation_date`, `deactivation_reason`, `student_id`, `employee_id`, `is_head`, `head_service`, `email_verified`, `verification_code`, `verification_expires`, `reset_code`, `reset_expires`, `failed_attempts`, `lockout_until`, `password_hash`, `admin_type`, `created_at`, `updated_at`, `is_archived`, `archived_at`, `archived_by`) VALUES
(1, 'Master Admin', 'admin@supportsystem.local', NULL, 'admin', 1, NULL, 'active', NULL, NULL, NULL, NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$HvyPvy8fRMusvjEwfaXLX.um4.CFOR0vw74Jfmi4Z80uUodmunHRK', 'master', '2026-03-30 15:20:27', '2026-09-24 08:01:16', 0, NULL, NULL),
(8, 'TEACHER AKOI', 'nierluxuria@gmail.com', NULL, 'teacher', 1, 'cs', 'active', NULL, NULL, NULL, '123', 1, 'clinic', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$ArcfuOpymb8Z7.92YTmXi.ZRWfo4nIbNf/DYNgh4y.IvGNofWBHDm', NULL, '2026-03-31 10:11:26', '2026-09-09 10:26:48', 0, NULL, NULL),
(9, 'library', 'niergalaxia@gmail.com', NULL, 'teacher', 1, 'cs', 'active', NULL, NULL, NULL, '1234', 1, 'library', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$AnDum2xVxts5/rddsOLuvexVNgzZ2zEMQx7ricXqB0pvTLHADriYy', NULL, '2026-03-31 10:36:35', '2026-08-27 10:55:14', 0, NULL, NULL),
(10, 'ssc/scholarhip ako', 'megumikato709@gmail.com', NULL, 'teacher', 1, 'css', 'active', NULL, NULL, NULL, 'awaw', 1, 'ssc_scholarship', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$/HBSodPEQLb0a1MF9HutEezFvQS4T3cl0YoQOHPDU/DZhoFdLIUia', NULL, '2026-03-31 12:36:33', '2026-05-31 12:18:41', 0, NULL, NULL),
(16, 'Guidance', 'fernand2fable@gmail.com', NULL, 'teacher', 1, 'Bachelor of Science in Criminology', 'active', NULL, NULL, NULL, '123ss', 1, 'guidance', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$ZxmAFFKme/8bWKkvbFAt7.97Ozqwn7Tpju.FkSpBksR4UnZnzRBOK', NULL, '2026-04-18 08:34:29', '2026-09-10 09:24:57', 0, NULL, NULL),
(36, 'Ley Harvie Peralta', 'leyharviep@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Computer Science 4', 'graduated', '2026-06-19', 'Graduated from program', '23-02036', NULL, 0, 'none', 1, NULL, NULL, '663407', '2026-09-10 20:26:07', 0, NULL, '$2y$10$QSJq/g6RQ6wad89sPXc3m.6PaI1ZMcGRCjv/r90GHR2GM647xdihC', NULL, '2026-05-27 10:29:13', '2026-09-24 08:04:34', 0, NULL, NULL),
(46, 'Jasmin Arachea', 'aracheajasmin@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Computer Science 4', 'graduated', '2026-06-20', 'Graduated from program', '23-02320', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$GtnHmRGbri2RF.5CPCErPex4ZHugOsm22xfU1p1K8xYVYroVb7dHG', NULL, '2026-05-30 10:17:21', '2026-09-10 20:02:42', 0, NULL, NULL),
(48, 'Adrian Clyde', 'kaiijasmin266@gmail.com', NULL, 'teacher', 1, 'Bachelor of Science in Computer Science', 'active', NULL, NULL, NULL, '009', 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$OfrapunmHSKCzJms7rZLkeAGbHf//jjVixtYVqgiJ8o3ke.xvzocS', NULL, '2026-05-30 10:30:04', '2026-09-18 16:17:20', 0, NULL, NULL),
(59, 'Aa Ss Aa', 'juurok16yakuzenyaku@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Accountancy 4', 'graduated', '2026-06-20', 'Graduated from program', '22-22222', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$u46oy0DUCt3IrfIEv86/6.Of.hJISmIZk6iwCq5T55bFuQHPJgNcO', NULL, '2026-06-20 09:21:14', '2026-09-02 11:07:51', 1, '2026-09-02 11:07:51', 1),
(68, 'Xzujien Raut-raut', 'elmarrautraut.bscs.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Computer Science 4', 'active', NULL, NULL, '23-02310', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$dwEZ2n4KaNgVDXUCxN3xpu8oSeHzWjxrPUjWw619btaH/2SHRkxPm', NULL, '2026-08-05 10:52:38', '2026-08-05 10:52:38', 0, NULL, NULL),
(69, 'Almirah Barreto Peralta', 'almirahperalta.bsa.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Accountancy 1', 'active', NULL, NULL, '26-03790', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$8ASxMvjc2fzg5Q64OiNyreHTCqzX5vXmmQVLyHwIKy1o.ikns7LRW', NULL, '2026-09-05 09:40:31', '2026-09-05 09:40:31', 0, NULL, NULL),
(70, 'Lee Mar Dizon Balan', 'leemarbalan.bsa.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Accountancy 1', 'active', NULL, NULL, '26-04121', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$cLpDT1KZpwpPKfbE4Phq9.gLPLQfZKJpKPQ5ldg.t5zuaj7zau7A.', NULL, '2026-09-10 20:35:04', '2026-09-10 20:35:04', 0, NULL, NULL),
(71, 'Amyed Dineros Cayabyab', 'amyedcayabyab.bsa.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Accountancy 1', 'active', NULL, NULL, '26-03935', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$CJ9DeOLwh42bcIT..0/KMOlNDjJuZGVdm/pt2Y6Ormb64iUgek6Cu', NULL, '2026-09-11 10:43:19', '2026-09-11 10:43:19', 0, NULL, NULL),
(72, 'Janine Untalasco', 'janineuntalasco.bsa.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Accountancy 4', 'active', NULL, NULL, '23-02199', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$9CS8rxp29VJVvZZHV1WAN.8mHV.WUO.rpxiY3eXpS1XABhGSzK73a', NULL, '2026-09-13 09:54:58', '2026-09-13 09:54:58', 0, NULL, NULL),
(73, 'Jefford Apostol Anchet', 'jeffordapostolancheta17@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Accountancy 1', 'active', NULL, NULL, '26-04011', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$ErPD0/A5pQ8YvMJHv6PM5Onqa/5CuaM3XcLKDOHAVmeugdC0/unb6', NULL, '2026-09-16 20:26:52', '2026-09-16 20:26:52', 0, NULL, NULL),
(74, 'Arianne Verzosa', 'arianneverzosa01@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Accountancy 1', 'active', NULL, NULL, '26-04186', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$7c37/efS4LQ/jCXjtxZDqO3d5ZtbQzdK3WIlqzxPEttrVB3eeFiM6', NULL, '2026-09-16 20:33:29', '2026-09-16 20:33:29', 0, NULL, NULL),
(75, 'Adrian Cebu', 'adriancebu188@gmail.com', NULL, 'teacher', 1, 'Bachelor of Science in Computer Science', 'active', NULL, NULL, NULL, '5201320', 1, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$QKFreUA91pEN2jBhopHeyO1uG3z3P.5x/Rd6ls.goTPG9fk0k4EkS', NULL, '2026-09-23 11:13:48', '2026-09-23 11:13:48', 0, NULL, NULL),
(76, 'Ersie Pol Francisco', 'yamai6rokyuzuru@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 2', 'active', NULL, NULL, '25-03585', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$vIa0zpzNS3E19gpbSYYvhOKe1vSiq1hxO7kuyT5qA5ME7tzxL7J42', NULL, '2026-09-23 11:30:15', '2026-09-23 11:33:13', 0, NULL, NULL),
(77, 'John Lloyd Malano', 'malanojohnlloyd019@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02102', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$bSjkKnkf6lRVGFZZedCQuey.5XfcC1TFjbR7vXHOvxfW.DtMRvnni', NULL, '2026-09-23 16:39:39', '2026-09-23 16:39:39', 0, NULL, NULL),
(78, 'Leyharvie Barreto Peralta', 'leyharvieperalta15.bscs.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Computer Science 4', 'active', NULL, NULL, '24-02036', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$t3SnBLM3CZMi0SyGmh2/Veg4vbQiYWq7KRe8saugM/GMgrRLY1hPe', NULL, '2026-09-23 16:43:17', '2026-09-23 16:43:17', 0, NULL, NULL),
(79, 'Natasha Mae Lantecsi', 'natashamaelantecsi.bshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02340', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 1, NULL, '$2y$10$/KjFi6y30BI8aaIFZtM32.I/dJHW11ZAbnHXLus65yTaeyfsDtLRq', NULL, '2026-09-23 16:46:01', '2026-09-23 19:21:18', 0, NULL, NULL),
(80, 'Rodelyn Doctor', 'rodelyndoctor15@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02071', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$fSYXWrg5WoKRrHtwL1C/XOeKRVAqHm0kYdrnXhl.pA1bomN7k4oz6', NULL, '2026-09-23 16:54:50', '2026-09-23 16:54:50', 0, NULL, NULL),
(81, 'Tricia Mae Tigno', 'triciamaetigno.bshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02114', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$kvMyJ5/IDz3PUzvIybldQ..49V6Rth4UU1EM4.dNVnqvEErXDBatG', NULL, '2026-09-23 16:55:08', '2026-09-23 16:55:08', 0, NULL, NULL),
(82, 'Rose Patrice Hernal', 'rosepatricehernal.bshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-01999', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 1, NULL, '$2y$10$UCPTUaJlA0I3EZATK.Zw3OAHry9/S.cS.8pCFTB.29yPzschpR1Ou', NULL, '2026-09-23 17:03:35', '2026-09-23 17:24:20', 0, NULL, NULL),
(83, 'Kristine Diaz', 'kristinediaz.bshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02010', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$BJFdW9fq70ITjQU7CKy33OhCsN8h583fTxRmdY2rH0ByIuN0LimXm', NULL, '2026-09-23 17:04:11', '2026-09-23 17:04:11', 0, NULL, NULL),
(84, 'Eljhee Mae Enate', 'eljheemaeg.enatebshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02130', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$yl2/yTGXAXUKmuGTDETrUOFPcawvn/4EoHFpzIfpsYNBNhS5OwOU.', NULL, '2026-09-23 17:04:23', '2026-09-23 17:04:23', 0, NULL, NULL),
(85, 'Shaile Anne Espinoza', 'shaileanneespinoza.bshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02103', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$rmyDJp4PzsJCrJpdeemRj.OEyp1bE94uUw8nXkA61Go.Xcsb1XKRm', NULL, '2026-09-23 17:04:28', '2026-09-23 17:04:28', 0, NULL, NULL),
(86, 'Wengel Divinagracia', 'wengeldivinagracia.bshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02201', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$Zma2zNBqDkFQPK/aoPHb4e/0pDFxLcYOKS2oi95i2e71EtIshGEfe', NULL, '2026-09-23 17:04:33', '2026-09-23 17:04:33', 0, NULL, NULL),
(87, 'Carl Jhustine Borbon', 'borboncarljhustine@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02155', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$unN1a48Dq751D6MXuJyUMOuIoV39CUCLg7gTN81MxbiDM0TBJGmDC', NULL, '2026-09-23 17:04:37', '2026-09-23 17:04:37', 0, NULL, NULL),
(88, 'Raquel Macadenden', 'macadendenraquel8@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '24-02546', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$GROEOtdExZDlW9fg6MOwBe5DhHh70WfQnJe0Gacf.xDQzOhv427XW', NULL, '2026-09-23 17:04:42', '2026-09-23 17:04:42', 0, NULL, NULL),
(89, 'Rica Mae Pobletin', 'pobletinricamae@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-01237', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$dtV.1VZsHnWtOzinJc67x.gaUmB5xQFdAYFw3NEO1byJGuv1fnCSC', NULL, '2026-09-23 17:04:46', '2026-09-23 17:04:46', 0, NULL, NULL),
(90, 'Justine Quiliza', 'justinequiliza.bshm1.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02384', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$BO0MwodvP1340q4jBxS91ODJ9hKxYxuTMH2lNpiE5enZ0ZT5md.ee', NULL, '2026-09-23 17:04:51', '2026-09-23 17:04:51', 0, NULL, NULL),
(91, 'Maria Maricolle Jubilado', 'mariamaricollejubilado.bshm.pass@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-01985', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$j38BgA/6u/gtxWtx.Zcsmudq.Xd5vTaQmMVnMrzklOgLwofV6uuzW', NULL, '2026-09-23 17:04:55', '2026-09-23 17:04:55', 0, NULL, NULL),
(92, 'Jeniefer Obice', 'obicejenieferd@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02259', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$iNpp0wSguXKlNlgX2PHzZerpkzRDXI.OvRhDcgN28ttzoRUxlvrLu', NULL, '2026-09-23 17:05:01', '2026-09-23 17:05:01', 0, NULL, NULL),
(93, 'John Christian Camero', 'camerojohnchristian@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02319', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$fz4ynGGOLc3CMmewUXwmC.Zxft5Hry0TNlx5zl4jtFh2hjJK0bgI2', NULL, '2026-09-23 17:05:05', '2026-09-23 17:05:05', 0, NULL, NULL),
(94, 'John Almartin Elorde', 'johnalmartinelorde@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02204', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$CdbsDzzvEW7izfWTGMqBGept4GdpJ930LOxOe89maFdjAavSf8MNi', NULL, '2026-09-23 17:05:11', '2026-09-23 17:05:11', 0, NULL, NULL),
(95, 'Michael Angelo Cacho', 'angelotorrescacho@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02165', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$ViyZ8rvk4tSzXvmeVZTPtenyNOe0j6azZ6gT0Zpm0/I6dsAzS5W2e', NULL, '2026-09-23 17:05:15', '2026-09-23 17:05:15', 0, NULL, NULL),
(96, 'Alicia Nikolle Orfinada', 'alicianikolle29@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02232', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$56/LDJjCpWn4pu2.L3yUh.lehVFmBhT/QQNsp73gRV/2ZRiJLZcJu', NULL, '2026-09-23 17:05:20', '2026-09-23 17:05:20', 0, NULL, NULL),
(97, 'Mary Joy Mercado Areniego', 'maryjoyareniego0@gmail.com', NULL, 'student', 1, 'Bachelor of Science in Hospitality Management 4', 'active', NULL, NULL, '23-02014', NULL, 0, 'none', 1, NULL, NULL, NULL, NULL, 0, NULL, '$2y$10$PkwuXACnkKgahRNcThIKDOxDk6yZhF2uJY7wqL502Gl4yb6H9bR8i', NULL, '2026-09-23 20:33:00', '2026-09-23 20:33:00', 0, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_knowledge`
--
ALTER TABLE `ai_knowledge`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_knowledge_active` (`is_active`),
  ADD KEY `idx_ai_knowledge_type` (`entry_type`);

--
-- Indexes for table `ai_settings`
--
ALTER TABLE `ai_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `announcement_comments`
--
ALTER TABLE `announcement_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcement` (`announcement_type`,`announcement_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `announcement_likes`
--
ALTER TABLE `announcement_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`user_id`,`announcement_type`,`announcement_id`),
  ADD KEY `idx_announcement` (`announcement_type`,`announcement_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `carousel_images`
--
ALTER TABLE `carousel_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_path` (`image_path`),
  ADD KEY `idx_login_type` (`login_type`),
  ADD KEY `idx_order` (`display_order`);

--
-- Indexes for table `case_reported_persons`
--
ALTER TABLE `case_reported_persons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `case_reporters`
--
ALTER TABLE `case_reporters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `clinic_announcements`
--
ALTER TABLE `clinic_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `clinic_clearance_requests`
--
ALTER TABLE `clinic_clearance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `clinic_feedback`
--
ALTER TABLE `clinic_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `moderated_by` (`moderated_by`);

--
-- Indexes for table `clinic_health_data`
--
ALTER TABLE `clinic_health_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`record_date`);

--
-- Indexes for table `clinic_health_records`
--
ALTER TABLE `clinic_health_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_record` (`user_id`);

--
-- Indexes for table `clinic_medicines`
--
ALTER TABLE `clinic_medicines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinic_medicine_batches`
--
ALTER TABLE `clinic_medicine_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_per_medicine` (`medicine_id`,`batch_number`);

--
-- Indexes for table `clinic_medicine_categories`
--
ALTER TABLE `clinic_medicine_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `clinic_status`
--
ALTER TABLE `clinic_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `clinic_treatments`
--
ALTER TABLE `clinic_treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `treated_by` (`treated_by`);

--
-- Indexes for table `clinic_visits`
--
ALTER TABLE `clinic_visits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `good_moral_feedback`
--
ALTER TABLE `good_moral_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student` (`student_id`);

--
-- Indexes for table `good_moral_records`
--
ALTER TABLE `good_moral_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `guidance_cases`
--
ALTER TABLE `guidance_cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `idx_guidance_cases_created_by` (`created_by`),
  ADD KEY `idx_guidance_cases_student_id` (`reported_student_id`),
  ADD KEY `idx_guidance_cases_teacher_id` (`teacher_id`),
  ADD KEY `idx_guidance_cases_counselor_id` (`counselor_id`),
  ADD KEY `idx_guidance_cases_reported_student_id` (`reported_student_id`);

--
-- Indexes for table `guidance_case_attachments`
--
ALTER TABLE `guidance_case_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_guidance_case_attachments_case_id` (`case_id`),
  ADD KEY `idx_guidance_case_attachments_uploaded_by` (`uploaded_by`);

--
-- Indexes for table `guidance_case_feedback`
--
ALTER TABLE `guidance_case_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_case_student` (`case_id`,`student_id`),
  ADD KEY `idx_case` (`case_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `guidance_observations`
--
ALTER TABLE `guidance_observations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_guidance_observations_case_id` (`case_id`),
  ADD KEY `idx_guidance_observations_observer_id` (`observer_id`);

--
-- Indexes for table `guidance_sessions`
--
ALTER TABLE `guidance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_guidance_sessions_case_id` (`case_id`),
  ADD KEY `idx_guidance_sessions_counselor_id` (`counselor_id`);

--
-- Indexes for table `library_books`
--
ALTER TABLE `library_books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `library_book_copies`
--
ALTER TABLE `library_book_copies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `library_borrows`
--
ALTER TABLE `library_borrows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `library_calendar`
--
ALTER TABLE `library_calendar`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `library_calendar_images`
--
ALTER TABLE `library_calendar_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `calendar_id` (`calendar_id`);

--
-- Indexes for table `library_categories`
--
ALTER TABLE `library_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `library_config`
--
ALTER TABLE `library_config`
  ADD PRIMARY KEY (`config_key`);

--
-- Indexes for table `library_feedback`
--
ALTER TABLE `library_feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `library_fines`
--
ALTER TABLE `library_fines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `borrow_id` (`borrow_id`);

--
-- Indexes for table `library_notifications`
--
ALTER TABLE `library_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `library_open_access_resources`
--
ALTER TABLE `library_open_access_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `library_reservations`
--
ALTER TABLE `library_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `library_resources`
--
ALTER TABLE `library_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `library_resource_categories`
--
ALTER TABLE `library_resource_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `library_visits`
--
ALTER TABLE `library_visits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pending_student_registrations`
--
ALTER TABLE `pending_student_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `scholarship_announcements`
--
ALTER TABLE `scholarship_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `scholarship_announcement_images`
--
ALTER TABLE `scholarship_announcement_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_application` (`student_id`,`announcement_id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `scholarship_document_validations`
--
ALTER TABLE `scholarship_document_validations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `announcement_id` (`announcement_id`),
  ADD KEY `validated_by` (`validated_by`);

--
-- Indexes for table `scholarship_updates`
--
ALTER TABLE `scholarship_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `scholarship_validation_feedback`
--
ALTER TABLE `scholarship_validation_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_feedback` (`validation_id`,`student_id`),
  ADD KEY `idx_validation` (`validation_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `ssaa_alumni`
--
ALTER TABLE `ssaa_alumni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_id` (`student_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `graduation_year` (`graduation_year`),
  ADD KEY `employment_status` (`employment_status`);

--
-- Indexes for table `ssaa_alumni_batch_emails`
--
ALTER TABLE `ssaa_alumni_batch_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_year` (`batch_year`),
  ADD KEY `email_status` (`email_status`);

--
-- Indexes for table `ssaa_alumni_career_milestones`
--
ALTER TABLE `ssaa_alumni_career_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumni_id` (`alumni_id`);

--
-- Indexes for table `ssaa_alumni_survey_questions`
--
ALTER TABLE `ssaa_alumni_survey_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employment_status` (`employment_status`),
  ADD KEY `is_default` (`is_default`);

--
-- Indexes for table `ssaa_alumni_survey_responses`
--
ALTER TABLE `ssaa_alumni_survey_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumni_id` (`alumni_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ssaa_audit_trail`
--
ALTER TABLE `ssaa_audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `action_type` (`action_type`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ssaa_feedback`
--
ALTER TABLE `ssaa_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `moderated_by` (`moderated_by`);

--
-- Indexes for table `ssaa_graduation_events`
--
ALTER TABLE `ssaa_graduation_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_date` (`event_date`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ssaa_student_progression`
--
ALTER TABLE `ssaa_student_progression`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_year` (`user_id`,`academic_year`),
  ADD KEY `evaluated_by` (`evaluated_by`);

--
-- Indexes for table `ssc_candidates`
--
ALTER TABLE `ssc_candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ssc_events`
--
ALTER TABLE `ssc_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ssc_event_photos`
--
ALTER TABLE `ssc_event_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `ssc_feedback`
--
ALTER TABLE `ssc_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `moderated_by` (`moderated_by`);

--
-- Indexes for table `ssc_initiatives`
--
ALTER TABLE `ssc_initiatives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ssc_officers`
--
ALTER TABLE `ssc_officers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_order` (`order_sequence`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_knowledge`
--
ALTER TABLE `ai_knowledge`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `announcement_comments`
--
ALTER TABLE `announcement_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `announcement_likes`
--
ALTER TABLE `announcement_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `carousel_images`
--
ALTER TABLE `carousel_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `case_reported_persons`
--
ALTER TABLE `case_reported_persons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=368;

--
-- AUTO_INCREMENT for table `case_reporters`
--
ALTER TABLE `case_reporters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `clinic_announcements`
--
ALTER TABLE `clinic_announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clinic_clearance_requests`
--
ALTER TABLE `clinic_clearance_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clinic_feedback`
--
ALTER TABLE `clinic_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clinic_health_data`
--
ALTER TABLE `clinic_health_data`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `clinic_health_records`
--
ALTER TABLE `clinic_health_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `clinic_medicines`
--
ALTER TABLE `clinic_medicines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `clinic_medicine_batches`
--
ALTER TABLE `clinic_medicine_batches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clinic_medicine_categories`
--
ALTER TABLE `clinic_medicine_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `clinic_status`
--
ALTER TABLE `clinic_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `clinic_treatments`
--
ALTER TABLE `clinic_treatments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinic_visits`
--
ALTER TABLE `clinic_visits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `good_moral_feedback`
--
ALTER TABLE `good_moral_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `good_moral_records`
--
ALTER TABLE `good_moral_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `guidance_cases`
--
ALTER TABLE `guidance_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `guidance_case_attachments`
--
ALTER TABLE `guidance_case_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `guidance_case_feedback`
--
ALTER TABLE `guidance_case_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `guidance_observations`
--
ALTER TABLE `guidance_observations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guidance_sessions`
--
ALTER TABLE `guidance_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `library_books`
--
ALTER TABLE `library_books`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `library_book_copies`
--
ALTER TABLE `library_book_copies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=353;

--
-- AUTO_INCREMENT for table `library_borrows`
--
ALTER TABLE `library_borrows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `library_calendar`
--
ALTER TABLE `library_calendar`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `library_calendar_images`
--
ALTER TABLE `library_calendar_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `library_categories`
--
ALTER TABLE `library_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `library_feedback`
--
ALTER TABLE `library_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `library_fines`
--
ALTER TABLE `library_fines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `library_notifications`
--
ALTER TABLE `library_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `library_open_access_resources`
--
ALTER TABLE `library_open_access_resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `library_reservations`
--
ALTER TABLE `library_reservations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `library_resources`
--
ALTER TABLE `library_resources`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `library_resource_categories`
--
ALTER TABLE `library_resource_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `library_visits`
--
ALTER TABLE `library_visits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `pending_student_registrations`
--
ALTER TABLE `pending_student_registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `scholarship_announcements`
--
ALTER TABLE `scholarship_announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `scholarship_announcement_images`
--
ALTER TABLE `scholarship_announcement_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `scholarship_document_validations`
--
ALTER TABLE `scholarship_document_validations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `scholarship_updates`
--
ALTER TABLE `scholarship_updates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scholarship_validation_feedback`
--
ALTER TABLE `scholarship_validation_feedback`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ssaa_alumni`
--
ALTER TABLE `ssaa_alumni`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `ssaa_alumni_batch_emails`
--
ALTER TABLE `ssaa_alumni_batch_emails`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ssaa_alumni_career_milestones`
--
ALTER TABLE `ssaa_alumni_career_milestones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ssaa_alumni_survey_questions`
--
ALTER TABLE `ssaa_alumni_survey_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `ssaa_alumni_survey_responses`
--
ALTER TABLE `ssaa_alumni_survey_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ssaa_audit_trail`
--
ALTER TABLE `ssaa_audit_trail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `ssaa_feedback`
--
ALTER TABLE `ssaa_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ssaa_graduation_events`
--
ALTER TABLE `ssaa_graduation_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ssaa_student_progression`
--
ALTER TABLE `ssaa_student_progression`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ssc_candidates`
--
ALTER TABLE `ssc_candidates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `ssc_events`
--
ALTER TABLE `ssc_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `ssc_event_photos`
--
ALTER TABLE `ssc_event_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `ssc_feedback`
--
ALTER TABLE `ssc_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ssc_initiatives`
--
ALTER TABLE `ssc_initiatives`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ssc_officers`
--
ALTER TABLE `ssc_officers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `case_reported_persons`
--
ALTER TABLE `case_reported_persons`
  ADD CONSTRAINT `case_reported_persons_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `guidance_cases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `case_reporters`
--
ALTER TABLE `case_reporters`
  ADD CONSTRAINT `case_reporters_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `guidance_cases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clinic_announcements`
--
ALTER TABLE `clinic_announcements`
  ADD CONSTRAINT `clinic_announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clinic_clearance_requests`
--
ALTER TABLE `clinic_clearance_requests`
  ADD CONSTRAINT `clinic_clearance_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `clinic_clearance_requests_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clinic_feedback`
--
ALTER TABLE `clinic_feedback`
  ADD CONSTRAINT `clinic_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `clinic_feedback_ibfk_2` FOREIGN KEY (`visit_id`) REFERENCES `clinic_visits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `clinic_feedback_ibfk_3` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clinic_health_data`
--
ALTER TABLE `clinic_health_data`
  ADD CONSTRAINT `clinic_health_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clinic_health_records`
--
ALTER TABLE `clinic_health_records`
  ADD CONSTRAINT `clinic_health_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clinic_medicine_batches`
--
ALTER TABLE `clinic_medicine_batches`
  ADD CONSTRAINT `clinic_medicine_batches_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `clinic_medicines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clinic_status`
--
ALTER TABLE `clinic_status`
  ADD CONSTRAINT `clinic_status_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `clinic_treatments`
--
ALTER TABLE `clinic_treatments`
  ADD CONSTRAINT `clinic_treatments_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `clinic_visits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `clinic_treatments_ibfk_2` FOREIGN KEY (`treated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `good_moral_feedback`
--
ALTER TABLE `good_moral_feedback`
  ADD CONSTRAINT `good_moral_feedback_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `good_moral_records`
--
ALTER TABLE `good_moral_records`
  ADD CONSTRAINT `good_moral_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `good_moral_records_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `guidance_case_feedback`
--
ALTER TABLE `guidance_case_feedback`
  ADD CONSTRAINT `fk_guidance_case` FOREIGN KEY (`case_id`) REFERENCES `guidance_cases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_guidance_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_book_copies`
--
ALTER TABLE `library_book_copies`
  ADD CONSTRAINT `library_book_copies_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_borrows`
--
ALTER TABLE `library_borrows`
  ADD CONSTRAINT `library_borrows_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `library_borrows_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_calendar_images`
--
ALTER TABLE `library_calendar_images`
  ADD CONSTRAINT `library_calendar_images_ibfk_1` FOREIGN KEY (`calendar_id`) REFERENCES `library_calendar` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_fines`
--
ALTER TABLE `library_fines`
  ADD CONSTRAINT `library_fines_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `library_fines_ibfk_2` FOREIGN KEY (`borrow_id`) REFERENCES `library_borrows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_notifications`
--
ALTER TABLE `library_notifications`
  ADD CONSTRAINT `library_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `library_notifications_ibfk_2` FOREIGN KEY (`reservation_id`) REFERENCES `library_reservations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_open_access_resources`
--
ALTER TABLE `library_open_access_resources`
  ADD CONSTRAINT `library_open_access_resources_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_reservations`
--
ALTER TABLE `library_reservations`
  ADD CONSTRAINT `library_reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `library_reservations_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `library_resources`
--
ALTER TABLE `library_resources`
  ADD CONSTRAINT `library_resources_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `library_resources_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `scholarship_announcements`
--
ALTER TABLE `scholarship_announcements`
  ADD CONSTRAINT `scholarship_announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_announcement_images`
--
ALTER TABLE `scholarship_announcement_images`
  ADD CONSTRAINT `scholarship_announcement_images_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `scholarship_announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_applications`
--
ALTER TABLE `scholarship_applications`
  ADD CONSTRAINT `scholarship_applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scholarship_applications_ibfk_2` FOREIGN KEY (`announcement_id`) REFERENCES `scholarship_announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_document_validations`
--
ALTER TABLE `scholarship_document_validations`
  ADD CONSTRAINT `scholarship_document_validations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scholarship_document_validations_ibfk_2` FOREIGN KEY (`announcement_id`) REFERENCES `scholarship_announcements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scholarship_document_validations_ibfk_3` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_updates`
--
ALTER TABLE `scholarship_updates`
  ADD CONSTRAINT `scholarship_updates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_validation_feedback`
--
ALTER TABLE `scholarship_validation_feedback`
  ADD CONSTRAINT `fk_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_validation` FOREIGN KEY (`validation_id`) REFERENCES `scholarship_document_validations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ssaa_alumni_career_milestones`
--
ALTER TABLE `ssaa_alumni_career_milestones`
  ADD CONSTRAINT `ssaa_alumni_career_milestones_ibfk_1` FOREIGN KEY (`alumni_id`) REFERENCES `ssaa_alumni` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ssaa_alumni_survey_responses`
--
ALTER TABLE `ssaa_alumni_survey_responses`
  ADD CONSTRAINT `ssaa_alumni_survey_responses_ibfk_1` FOREIGN KEY (`alumni_id`) REFERENCES `ssaa_alumni` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ssaa_feedback`
--
ALTER TABLE `ssaa_feedback`
  ADD CONSTRAINT `ssaa_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ssaa_feedback_ibfk_2` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ssc_candidates`
--
ALTER TABLE `ssc_candidates`
  ADD CONSTRAINT `ssc_candidates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ssc_events`
--
ALTER TABLE `ssc_events`
  ADD CONSTRAINT `ssc_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ssc_event_photos`
--
ALTER TABLE `ssc_event_photos`
  ADD CONSTRAINT `ssc_event_photos_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `ssc_events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ssc_feedback`
--
ALTER TABLE `ssc_feedback`
  ADD CONSTRAINT `ssc_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ssc_feedback_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `ssc_events` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ssc_feedback_ibfk_3` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ssc_initiatives`
--
ALTER TABLE `ssc_initiatives`
  ADD CONSTRAINT `ssc_initiatives_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ssc_officers`
--
ALTER TABLE `ssc_officers`
  ADD CONSTRAINT `ssc_officers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
