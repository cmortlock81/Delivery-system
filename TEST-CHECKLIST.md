# Speedy Couriers Dispatch Test Checklist

## Controller workflow

- [ ] Activate the plugin successfully.
- [ ] Confirm **Controller** and **Courier** roles were created.
- [ ] Log in as a controller.
- [ ] Open **Speedy Dispatch → Add New Job**.
- [ ] Create a new phone order with all required fields.
- [ ] Confirm an order number such as `SC-1001` is generated.
- [ ] Assign a courier at creation time.
- [ ] Confirm the job appears in **Dashboard** and **All Jobs**.
- [ ] Edit the job from the detail screen.
- [ ] Change the status and confirm a history entry is recorded.
- [ ] Use quick row update to reassign a courier.
- [ ] Use bulk actions to assign or change status for multiple jobs.

## Courier workflow

- [ ] Log in as a courier assigned to at least one job.
- [ ] Open **Speedy Dispatch → My Deliveries**.
- [ ] Confirm only assigned jobs appear.
- [ ] Mark a job **Out for Delivery**.
- [ ] Mark a job **Delivered**.
- [ ] Attempt to mark a job **Failed** without a reason and confirm validation blocks the request.
- [ ] Mark a job **Failed** with a reason and confirm the reason is saved.

## Permissions and security

- [ ] Confirm couriers cannot open the dispatch dashboard or settings.
- [ ] Confirm couriers cannot update jobs assigned to another courier.
- [ ] Confirm nonces are present on admin forms.
- [ ] Confirm protected handlers reject unauthorized access.
- [ ] Confirm all user-facing values are escaped in admin and frontend output.

## Frontend flows

- [ ] Enable frontend order submission in settings.
- [ ] Add `[speedy_couriers_order_form]` to a page.
- [ ] Submit a job and confirm it appears in admin with source `web`.
- [ ] Add `[speedy_couriers_track_order]` to a page.
- [ ] Track a valid order using order number and phone.
- [ ] Confirm tracking fails for an incorrect phone number.

## Regression checks

- [ ] Deactivate and reactivate the plugin without data loss.
- [ ] Confirm existing jobs still load after reactivation.
- [ ] Confirm settings persist after reactivation.
