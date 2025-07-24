describe('AI-Book E2E Tests', () => {
  beforeEach(() => {
    // Clear database before each test
    cy.clearDatabase()
    cy.seedDatabase()
  })

  it('should display the home page', () => {
    cy.visit('/')
    cy.contains('AI-Book Social Network')
    cy.get('[data-cy="app-title"]').should('be.visible')
  })

  it('should navigate to about page', () => {
    cy.visit('/')
    cy.get('[data-cy="nav-about"]').click()
    cy.url().should('include', '/about')
    cy.contains('About')
  })

  it('should handle login flow', () => {
    cy.visit('/login')
    
    // Fill in login form
    cy.get('[data-cy="email-input"]').type('test@example.com')
    cy.get('[data-cy="password-input"]').type('password123')
    cy.get('[data-cy="login-button"]').click()
    
    // Should redirect to dashboard on successful login
    cy.url().should('include', '/dashboard')
    cy.contains('Welcome')
  })

  it('should handle registration flow', () => {
    cy.visit('/register')
    
    // Fill in registration form
    cy.get('[data-cy="name-input"]').type('Test User')
    cy.get('[data-cy="email-input"]').type('newuser@example.com')
    cy.get('[data-cy="password-input"]').type('password123')
    cy.get('[data-cy="password-confirmation-input"]').type('password123')
    cy.get('[data-cy="register-button"]').click()
    
    // Should show success message or redirect
    cy.contains('Registration successful').or(cy.url().should('include', '/dashboard'))
  })

  it('should test API health endpoint', () => {
    cy.request('GET', `${Cypress.env('apiUrl')}/health`)
      .then((response) => {
        expect(response.status).to.eq(200)
        expect(response.body).to.have.property('status', 'ok')
        expect(response.body).to.have.property('sanctum', 'enabled')
      })
  })
}) 