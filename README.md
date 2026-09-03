# ERP for Nextcloud — Employee and Human Resources Management Module

**Developed by:** Luis Ángel  
**Version:** Beta 2026  
**License:** AGPL-3.0  

---

<p align="center">
  <a href="https://youtu.be/SRsn5LIbKTY">
    <img src="https://img.youtube.com/vi/SRsn5LIbKTY/hqdefault.jpg" alt="Employees ERP for Nextcloud - Demo" width="640">
  </a>
</p>

<p align="center">
  <strong>Watch the English demo video:</strong><br>
  <a href="https://youtu.be/WrfVQT97Mqk">Employees ERP for Nextcloud — Time Reports Update</a>
</p>

---

## 📚 Wiki

Before using this module, please visit the project wiki:

[Employees Module Wiki](https://github.com/Destripador/empleados/wiki)

---

## 📄 Overview

This ERP module for Nextcloud enables organizations to manage employee information, departments, positions, teams, documents, savings, vacations, absences, and work time reports directly inside the Nextcloud ecosystem.

It is designed for SMEs, accounting firms, professional service companies, and organizations that want digital sovereignty, process customization, and better control of internal employee data without depending entirely on external SaaS platforms.

The goal of this module is to provide a lightweight, internal, and adaptable ERP focused on human resources, employee documentation, organizational structure, and operational time control.

---

## 🏢 Current Features

### 🔹 Human Capital

#### Employees

* General employee information.
* Job and employment data.
* Banking information.
* Personal data such as RFC, IMSS, and CURP.
* Vacation history.
* Savings fund information.
* Work structure assignment.
* Partner, manager, and employee relationships.
* Assignment to departments and positions.
* Internal notes.
* Employee documents such as records, memos, identifications, and related files.

#### Departments

* Department creation and management.
* Employee assignment to departments.
* Department-based employee organization.

#### Positions

* Job position management.
* Assignment of employees to positions.

#### Teams

* Employee grouping under leaders, managers, or partners.
* Team management for internal organizational control.
* Support for structured work groups.

---

### 🔹 Savings Module

The module includes a savings management section that allows employees and administrators to manage savings-related operations inside Nextcloud.

* Employee savings fund request management.
* Employee request history.
* Administrative panel to review and authorize requests.
* Centralized savings information within the employee profile.

---

### 🔹 Vacations and Absences

The module includes tools to manage employee vacation periods and absence records.

* Absence and vacation calendar.
* Vacation history by employee.
* Anniversary-based vacation period management.
* Automatic calculation of vacation days according to the Mexican Federal Labor Law.
* Administrative review of employee absence records.
* Support for internal vacation and absence control.

> Note: Vacation calculations are currently adapted to Mexican labor rules.

---

### 🔹 Time Reports

The latest major update adds a complete time reporting workflow.

Employees can report their working time by client, project, and activity. Administrators can review this information through summaries, indicators, filters, and charts.

#### Employee features

* Time reporting by client, project, and activity.
* Quick time report access from the Nextcloud dashboard.
* Dashboard widget showing whether the employee has pending or completed reports.
* Internal reminders for missing time reports.
* Easier daily reporting workflow.

#### Administrative features

* Administrative summary by selected period.
* Review of reported and pending employees.
* Period-based report analysis.
* Filters by year, period, employee, client, project, and activity.
* Administrative charts to analyze workload distribution.
* Configurable group access for administrative report views.
* Support for importing and exporting time report information.

#### Time report KPIs

* Reported employees.
* Pending employees.
* Total records.
* Total reported minutes.
* Total reported hours.
* Estimated salary.
* Compliance percentage.

---

### 🔹 Documents

Employee document management is integrated with Nextcloud Files.

* Employee document management.
* Automatic employee folder creation.
* Storage of employee files inside a configured Nextcloud user account.
* Centralized document organization under an `EMPLEADOS` folder.
* Useful for records, memos, identification files, and internal employee documentation.

---

### 🔹 Configuration

The module includes configuration options to adapt it to the organization.

* Global module settings.
* Configurable Data Manager user.
* Configurable administrative access group for reports.
* Internal options to adapt the module to the organization.
* Settings for managing how employee documents and reports are handled.

---

## 🎥 Video Demonstration

### Time Reports Update — English Demo

[![Employees ERP for Nextcloud - Time Reports Update](https://img.youtube.com/vi/WrfVQT97Mqk/hqdefault.jpg)](https://youtu.be/WrfVQT97Mqk)

[Watch on YouTube](https://youtu.be/WrfVQT97Mqk)

---

## ⚙️ Technical Requirements

* Nextcloud 28+
* PHP 8.1+
* MariaDB or MySQL supported by Nextcloud
* Nextcloud custom app installation access
* Command line access to run `occ` commands when required

---

## 🧰 Installation Guide

### 1. Download the compiled version

Visit the latest release and download the `.tar.gz` file.

### 2. Extract and install

Place the extracted `empleados` folder into your Nextcloud `custom_apps` directory.

Example:

```bash
/path/to/nextcloud/custom_apps/empleados
```

### 3. Enable the app

From your Nextcloud installation, run:

```bash
php occ app:enable empleados
```

### 4. Run migrations if needed

If your Nextcloud installation is in developer mode or requires manual migration execution, run the corresponding migration command:

```bash
php occ migrations:execute empleados <version>
```

Replace `<version>` with the required migration version.

### 5. Assign the Data Manager

Go to the **Configurations** tab of the Employees module in the admin panel and set the **Data Manager User**.

This user will store employee documents in a folder called `EMPLEADOS` inside their Nextcloud files.

When a new employee is activated, their personal folder is automatically created in that location.

---

## 🧭 Basic Usage

### Employee Management

Use the Employees section to register and manage employee information, including personal data, job details, banking information, documents, notes, vacations, and savings records.

### Departments and Positions

Create departments and positions to organize employees according to the company structure.

### Teams

Create work teams and assign employees under leaders, managers, or partners.

### Savings

Employees can create savings-related requests, while administrators can review and manage them from the administrative panel.

### Vacations and Absences

Use the vacation calendar and absence records to track employee availability and vacation history.

### Time Reports

Employees can report working time by client, project, and activity.

Administrators can review period-based summaries, KPIs, charts, pending reports, completed reports, and workload distribution.

---

## 🔐 Permissions and Access Control

The module includes access control for sensitive areas.

Administrative report views can be restricted using a configurable group. This allows organizations to define who can review employee reports, summaries, and administrative time report information.

---

## 🔄 Upcoming Roadmap

1. Complete and refine the full vacations and absences workflow.
2. Improve downloadable reports in Excel and PDF.
3. Refine roles and permissions.
4. Expand technical and user documentation.
5. Improve administrative dashboards.
6. Prepare a stable open-source release.
7. Continue improving the time reporting workflow.
8. Add more reporting and analytics options.

---

## 🚀 Current Status

* Functional module in internal production environment.
* Public beta in preparation.
* Documentation in progress.
* Time reports module recently added and under active improvement.
* Designed as an internal ERP module for Nextcloud-based organizations.

---

## 🔒 License

GNU Affero General Public License v3.0 — Free software for use, modification, and distribution under the terms of the license.

Because this project uses AGPL-3.0, modifications and network-based usage may require source code disclosure according to the terms of the license.

---

## 🤝 Contributions

This module is intended for internal use and collaboration with other firms or organizations interested in customized Nextcloud ERP solutions.

Contributions, suggestions, testing, and documentation improvements are welcome.

---

## 📌 Notes

This project is still in beta. Some features may change as the module evolves.

Use it carefully in production environments and always test updates before applying them to critical systems.
