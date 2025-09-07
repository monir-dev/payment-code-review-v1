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
