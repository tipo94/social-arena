// ***********************************************************
// This example support/e2e.ts is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'

// Custom commands for AI-Book testing
declare global {
  namespace Cypress {
    interface Chainable {
      /**
       * Custom command to log in via API
       * @example cy.login('user@example.com', 'password')
       */
      login(email: string, password: string): Chainable<void>
      
      /**
       * Custom command to seed database
       * @example cy.seedDatabase()
       */
      seedDatabase(): Chainable<void>
      
      /**
       * Custom command to clear database
       * @example cy.clearDatabase()
       */
      clearDatabase(): Chainable<void>
    }
  }
} 