<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareHub - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 0L19.9829 14.0171L34 17L19.9829 19.9829L17 34L14.0171 19.9829L0 17L14.0171 14.0171L17 0Z" fill="white"/><path d="M17 9.13158L18.6095 15.3905L24.8684 17L18.6095 18.6095L17 24.8684L15.3905 18.6095L9.13158 17L15.3905 15.3905L17 9.13158Z" fill="#00A9FF"/></svg>
                <h1>CareHub</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="dashboard.html" class="active">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 13H11V3H3V13ZM3 21H11V15H3V21ZM13 21H21V11H13V21ZM13 3V9H21V3H13Z" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Dashboard
                    </a></li>
                    <li><a href="schedule.html">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w.org/2000/svg"><path d="M8 7V3M16 7V3M3 11H21M5 21H19C20.1046 21 21 20.1046 21 19V7C21 5.89543 20.1046 5 19 5H5C3.89543 5 3 5.89543 3 7V19C3 20.1046 3.89543 21 5 21Z" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Schedule
                    </a></li>
                    <li><a href="assign-elderly.html">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21M17 7C17 9.20914 15.2091 11 13 11C10.7909 11 9 9.20914 9 7C9 4.79086 10.7909 3 13 3C15.2091 3 17 4.79086 17 7ZM23 21V19C22.9992 18.2323 22.7551 17.4842 22.3023 16.8525C21.8496 16.2208 21.2111 15.7394 20.47 15.48M17 3C17.7946 3.23595 18.4114 3.65345 18.8995 4.19539C19.3876 4.73733 19.7283 5.38136 19.89 6.1" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Assign Elderly
                    </a></li>
                    <li><a href="notification.html">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8ZM13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Notification
                    </a></li>
                    <li><a href="update-care.html">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4C3.46957 4 2.96086 4.21071 2.58579 4.58579C2.21071 4.96086 2 5.46957 2 6V20C2 20.5304 2.21071 21.0391 2.58579 21.4142C2.96086 21.7893 3.46957 22 4 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V13M18.5 2.5C18.8978 2.10218 19.4374 1.87868 20 1.87868C20.5626 1.87868 21.1022 2.10218 21.5 2.5C21.8978 2.89782 22.1213 3.43739 22.1213 4C22.1213 4.56261 21.8978 5.10218 21.5 5.5L12 15L8 16L9 12L18.5 2.5Z" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Update care
                    </a></li>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <header class="header">
                <div class="header-title">
                    <p>Welcome back, Jame!</p>
                    <h1>Dashboard</h1>
                </div>
                <div class="user-profile">
                    <div class="avatar"></div>
                    <div class="user-details">
                        <h3>Jame Jh.</h3>
                        <p>Carer</p>
                    </div>
                </div>
            </header>
            <div class="content-wrapper">
                <div class="dashboard-grid">
                    <div class="card assigned-elderly">
                        <h2 class="card-header">Assigned Elderly Today</h2>
                        <div class="content">
                            <div class="avatar" style="background-color: #d1e7dd;"></div>
                            <div class="details">
                                <h3>Ms. Emily Cr.</h3>
                                <p>Room: 5A</p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary">See details</button>
                        </div>
                    </div>
                    <div class="card medicine">
                        <h2 class="card-header">Medicine</h2>
                        <ul>
                            <li><span>A Medicine</span> <span>50 mg</span></li>
                            <li><span>B Medicine</span> <span>50 mg</span></li>
                            <li><span>C Medicine</span> <span>50 mg</span></li>
                        </ul>
                    </div>
                    <div class="card schedule-card">
                        <h2 class="card-header">Schedule</h2>
                        <table class="schedule-table">
                            <tbody>
                                <tr>
                                    <td>07:00 AM</td>
                                    <td>Wake-up call</td>
                                    <td>Greet calmly, open curtains, offer water.</td>
                                </tr>
                                <tr>
                                    <td>07:30 AM</td>
                                    <td>Breakfast</td>
                                    <td>Tea & toast, ensure hearing aid and glasses are on.</td>
                                </tr>
                                <tr>
                                    <td>08:30 AM</td>
                                    <td>Shower & dressing</td>
                                    <td>Assist fully, check non-slip mat and support rail.</td>
                                </tr>
                                <tr>
                                    <td>09:00 AM</td>
                                    <td>Medication reminder</td>
                                    <td>Confirm she takes it, may forget or ask twice.</td>
                                </tr>
                                 <tr>
                                    <td>10:00 AM</td>
                                    <td>Light activity</td>
                                    <td>She enjoys calm activities classical music is ideal.</td>
                                </tr>
                            </tbody>
                        </table>
                         <div class="card-footer">
                            <button class="btn btn-primary">See More</button>
                        </div>
                    </div>
                    <div class="card note-card">
                        <h2 class="card-header">Note</h2>
                        <ul>
                           <li><strong>Showering/Dressing:</strong> Needs help, especially with lower clothes. Check non-slip mat.</li>
                           <li><strong>Medication:</strong> Take after each meal 50 mg 3 times a day</li>
                           <li><strong>Essentials:</strong> Keep glasses, hearing aids, slippers visible (beside table).</li>
                        </ul>
                        <div class="card-footer">
                            <button class="btn btn-primary">See details</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>