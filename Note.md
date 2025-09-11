# Understand the task
- I have to implement the re-billing (subscription) flow that works with existing functionality.

# Initial tasks/investigation before I proceed with actual work.
- Run the project locally.
  - Fixed composer install issue by running `composer update`
  - Project run, but it's missing `NMI_API_KEY`, thugs it was creating errors. 
  - Explored sandbox environment and investigate documentations to get the demo API key. 
  - I found demo api key here: https://docs.nmi.com/reference/testing-methods
  - Since the NmiPaymentGateway.php using `https://secure.nmi.com/api/v2/three-step` this url that's why I didn't used the sanbox environment.
  - Fixed existing test by removing deprecation related tags form `phpunit.dist.xml`
- 

# Planning phase to determine work process
- Investigate documentation `https://secure.nmi.com/merchants/resources/integration/integration_portal.php?tid=4a0d25146526480a75f81a71f616c04f#3step_methodology` for re-billing flow.
- Very basic implementation as like the projects existing code.
- First I will do it separately. then I will merge with current checkout process.
- Implement Unit/Integration tests
- Refactor to DDD

# Finding related to re-billing.
- First I need a subscription plan.
- When I create subscription, I have to pass plan ID and card information.
- I can't find any endpoints to get all the plans from the API.

# Steps to follow.
- Create plan and save it to local database.
- For now, I will create separate subscription page where I will be able to choose plan.

# Basic plan, subscription, and re-billing
- Basic implementation of subscription plan
- Separate basic implementation of subscription creation and api
- Re-bill feature inside subscription.

# Next Ongoing work...
- A command for processing all active subscriptions re-bill based on due date
- Subscribe feature when create a checkout/transaction.
- Lastly refactor code to DDD.

# Summarizing tasks so far
- Project full flow works. Codes and tests are not in good shape though.
- Now I will focus on refactoring.

# Added two more notes for more details
- SIMPLE_SETUP.md
- DDD_TRANSFORMATION_NOTES.md
