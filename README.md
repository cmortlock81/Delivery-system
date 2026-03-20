# Speedy Couriers Dispatch

A production-minded WordPress plugin that turns WordPress admin into a lightweight courier dispatch system for **Speedy Couriers**.

## Why the MVP uses a Custom Post Type

For the MVP, delivery jobs are stored as a dedicated Custom Post Type (`scd_job`) with structured post meta.

This approach was chosen because it gives the plugin:

- native WordPress CRUD, timestamps, and permissions integration
- reliable `WP_Query` support for dashboard and courier screens
- simple extensibility for future REST, reporting, and notifications work
- no extra schema-management burden while the workflow is still evolving

A custom table can be introduced later if dispatch volume or reporting needs outgrow the CPT/meta approach.

## Features

- Dispatch dashboard with summary cards and recent jobs
- Fast manual phone-order entry for controllers
- Dedicated all-jobs screen with filters, sorting, quick assignment, and bulk actions
- Job detail/edit screen with audit trail and status history
- Courier-only “My Deliveries” screen showing assigned jobs only
- Courier status actions for `out_for_delivery`, `delivered`, and `failed`
- Required failed reason when a courier marks a job as failed
- Settings page for company name, order prefix, priority default, frontend form, and tracking
- Frontend order submission shortcode
- Frontend tracking shortcode
- Role and capability model for administrators, controllers, and couriers
- Notification-ready hooks for job created, assigned, and status-changed events

## Folder structure

```text
speedy-couriers-dispatch.php
admin/
  class-scd-admin.php
  class-scd-jobs-list-table.php
assets/
  css/
    admin.css
includes/
  class-scd-activator.php
  class-scd-deactivator.php
  class-scd-helpers.php
  class-scd-job-repository.php
  class-scd-plugin.php
  class-scd-roles.php
  class-scd-settings.php
  class-scd-statuses.php
public/
  class-scd-public.php
README.md
```

## Data captured per delivery job

Each job stores:

- order number
- source (`phone` or `web`)
- customer name
- customer phone
- recipient name
- recipient phone
- delivery address
- delivery notes
- priority (`normal` or `urgent`)
- status (`new`, `assigned`, `out_for_delivery`, `delivered`, `failed`, `cancelled`)
- assigned courier user ID
- created by user ID
- created timestamp
- updated timestamp
- failed delivery reason
- status history entries

## Order numbers

Order numbers are generated sequentially using the configured prefix.

Examples:

- `SC-1001`
- `SC-1002`
- `SC-1003`

The plugin maintains an internal sequence option and verifies uniqueness before saving.

## Roles and capabilities

### Administrator

- full dispatch access
- settings access
- all job management

### Controller (`scd_controller`)

- view dispatch dashboard
- create jobs
- edit jobs
- assign couriers
- change statuses
- view all jobs

### Courier (`scd_courier`)

- view only assigned jobs
- update only assigned jobs
- mark assigned jobs as out for delivery, delivered, or failed

## Admin workflow

### Dashboard

Shows:

- New jobs
- Assigned
- Out for Delivery
- Delivered today
- Failed

Also includes:

- recent jobs table
- status filter
- search across order number, names, phones, and address

### Add New Job

Allows controllers to create jobs manually after a phone call with:

- source
- customer details
- recipient details
- delivery address
- notes
- priority
- optional courier assignment
- initial status

### All Jobs

Includes:

- sortable list table
- status filters
- search
- row-level quick assignment and status updates
- bulk assignment
- bulk status changes

### Job Detail

Includes:

- full editable job data
- status history
- failed reason field
- created by / updated audit details

## Courier workflow

Couriers use the **My Deliveries** admin page to see only jobs assigned to their account.

Each courier card shows:

- order number
- recipient
- phone
- address
- notes
- current status
- priority

Courier actions:

- mark out for delivery
- mark delivered
- mark failed

If a courier marks a job as failed, a reason is required.

## Shortcodes

### Submit a delivery request

```text
[speedy_couriers_order_form]
```

Creates a new job with:

- `source = web`
- `status = new`

### Track an order

```text
[speedy_couriers_track_order]
```

Customers enter:

- order number
- customer or recipient phone number

## Hooks for future notifications

The MVP exposes these hooks:

- `scd_job_created`
- `scd_job_assigned`
- `scd_job_status_changed`

These make it straightforward to add email, SMS, WhatsApp, or webhook notifications later.

## Installation

1. Copy the plugin into `wp-content/plugins/speedy-couriers-dispatch`.
2. Activate **Speedy Couriers Dispatch** from WordPress admin.
3. Create or assign users to the **Controller** and **Courier** roles as needed.
4. Review plugin settings under **Speedy Dispatch → Settings**.
5. Optionally place shortcodes on frontend pages.

## Example test plan

### Controller workflow

- Activate the plugin.
- Confirm the new roles and capabilities exist.
- Log in as a controller.
- Create a new phone order.
- Assign a courier during creation.
- Verify the order number is generated.
- Verify the job appears on the dashboard and all-jobs screen.
- Edit the job and change status.
- Confirm the status history records the change.

### Courier workflow

- Log in as a courier assigned to a job.
- Confirm only assigned jobs appear on **My Deliveries**.
- Mark a job `out_for_delivery`.
- Mark a job `delivered`.
- Mark another job `failed` and confirm a failure reason is required.
- Confirm controllers can see those updates in admin.

### Permissions checks

- Confirm a courier cannot view the dashboard or all-jobs screen.
- Confirm a courier cannot update a job not assigned to them.
- Confirm non-authorized users cannot submit protected admin actions without capability and nonce checks.

### Frontend submission flow

- Enable the frontend order form setting.
- Add the `[speedy_couriers_order_form]` shortcode to a page.
- Submit a new job.
- Confirm the job appears in dispatch admin with source `web` and status `new`.
- Add the tracking shortcode and verify an order can be found only with the correct order number and phone.

## Future roadmap

- proof of delivery photo upload and signature capture
- Google Maps / geocoding integration
- SMS and email notifications
- pricing calculator and billing logic
- reporting dashboard with courier performance metrics
- customer portal with authenticated order history
- dedicated REST endpoints for mobile courier apps
- SLA timers and escalation alerts
