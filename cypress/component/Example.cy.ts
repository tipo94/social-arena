import { mount } from 'cypress/vue'

// Example component for testing
const TestComponent = {
  props: ['title', 'content'],
  template: `
    <div class="test-component">
      <h1>{{ title }}</h1>
      <p>{{ content }}</p>
      <button @click="$emit('click')">Click me</button>
    </div>
  `
}

describe('Component Testing Example', () => {
  it('should render component with props', () => {
    mount(TestComponent, {
      props: {
        title: 'Test Title',
        content: 'Test Content'
      }
    })

    cy.contains('Test Title')
    cy.contains('Test Content')
    cy.get('.test-component').should('be.visible')
  })

  it('should handle user interactions', () => {
    const onClickSpy = cy.spy().as('onClickSpy')
    
    mount(TestComponent, {
      props: {
        title: 'Interactive Test',
        content: 'Click the button',
        onClick: onClickSpy
      }
    })

    cy.get('button').click()
    cy.get('@onClickSpy').should('have.been.called')
  })

  it('should apply CSS classes correctly', () => {
    mount(TestComponent, {
      props: {
        title: 'Styled Component',
        content: 'This component has styles'
      }
    })

    cy.get('.test-component')
      .should('have.class', 'test-component')
      .and('be.visible')
  })
}) 