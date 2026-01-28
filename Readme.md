🏙️ Budapest Community Budget

📌 Project Overview

Budapest Community Budget is a web application that enables residents to actively participate in the city’s decision-making process. Users can submit project proposals and vote on ideas they support, helping shape community development through a transparent and structured system.

The application handles user authentication, project lifecycle management, and a rule-based voting mechanism with clearly defined constraints.

🎓 Course: Web Programming (PHP Assignment)
🛠️ Technologies: Vanilla PHP (no frameworks), HTML, CSS, JavaScript (AJAX / Fetch API), JSON-based data storage

⸻

✨ Features

👥 1. General & Guest Access
•	Project Listing – View all approved and published projects
•	Filtering – Browse projects by category (e.g., Green Budapest, Local Small Project)
•	Project Details – View full descriptions and live vote counts
•	Authentication – User registration and login system

⸻

👤 2. Authenticated Users
•	Project Submission
•	Submit new proposals with validation:
•	Title
•	Description
•	Category
•	Image
•	Valid district postal code
•	Project Management
•	Track personal project status:
•	Pending
•	Approved
•	Rejected
•	Rework requested
•	Voting System
•	Vote on published projects
•	Constraints:
•	Maximum 3 votes per category
•	Maximum 1 vote per project
•	Vote Withdrawal
•	Votes can be withdrawn within 2 weeks of project publication
•	Voting Period
•	Voting is disabled for projects published more than 2 weeks ago

⸻

🛡️ 3. Administrator
•	Moderation Dashboard
•	Review submitted projects
•	Actions
•	Approve – Publish project for voting
•	Reject – Deny project submission
•	Rework – Request changes with feedback
•	Statistics
•	View the most popular projects
•	See top projects per category

⸻

⚙️ Installation & Setup

1️⃣ Clone the repository

git clone https://github.com/your-username/budapest-community-budget.git

2️⃣ Navigate to the project directory

cd budapest-community-budget

3️⃣ Start the local PHP server

php -S localhost:8000

4️⃣ Access the application

Open your browser and go to:
👉 http://localhost:8000

⸻

🔐 Default Admin Credentials
•	Username: admin
•	Password: admin

⸻

📚 Notes
•	No PHP frameworks are used — the project is built entirely with vanilla PHP.
•	Data persistence is handled via JSON files, focusing on logic and structure rather than database complexity.
•	The project emphasizes validation, business rules, and clear role separation.
