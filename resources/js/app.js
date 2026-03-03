console.log('APP JS GELADEN')

import './bootstrap'

document.addEventListener('DOMContentLoaded', () => {
  console.log('DOM gestartet')

  const page = document.body.dataset.page

  if (page === 'local-seo') {
    console.log('local seo')
    import('./modules/localSeo')
  }

  if (page === 'crawler') {
    //import('./modules/crawler')
  }

  if (page === 'report-show') {
    //import('./modules/reportShow')
  }

})
