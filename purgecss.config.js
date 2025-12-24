module.exports = {
  content: [
    './index.html',
    './**/*.html',
    './**/*.js'
  ],

  css: [
    './assets/css/main.css'
  ],

  output: './assets/css/clean',

  safelist: {
    standard: [
      /* Bootstrap estados */
      'active', 'show', 'fade', 'collapse',
      'modal', 'modal-open', 'modal-backdrop',
      'dropdown', 'dropdown-menu', 'dropdown-item',
      'navbar', 'navbar-collapse', 'navbar-toggler',
      'offcanvas', 'offcanvas-backdrop',

      /* Utilidades Bootstrap */
      'container', 'row', 'col',
      'd-flex', 'd-none', 'd-block',
      'position-relative', 'position-absolute',
      'visible', 'invisible',

      /* JS / estados custom */
      'is-active', 'is-visible', 'is-hidden'
    ],

    deep: [
      /* Bootstrap */
      /^btn-/,
      /^col-/,
      /^row/,
      /^container/,
      /^navbar-/,
      /^dropdown-/,
      /^modal-/,
      /^offcanvas-/,
      /^alert-/,
      /^bg-/,
      /^text-/,
      /^border-/,
      /^shadow-/,

      /* AOS */
      /^aos-/,

      /* Swiper */
      /^swiper-/,

      /* GLightbox */
      /^glightbox/,
      /^gslide/,
      /^gdesc/,

      /* Isotope */
      /^grid/,
      /^filter/,
      /^isotope/
    ],

    greedy: [
      /* Hover dinámico */
      /:hover$/,
      /:focus$/,
      /:active$/,

      /* Animaciones */
      /^animate__/,
      /^fade/,
      /^zoom/,
      /^slide/,

      /* PureCounter */
      /^purecounter/
    ]
  }
}



