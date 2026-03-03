console.log('LOCAL SEO MODULE GELADEN')

const form = document.getElementById('local-seo-form')
if (!form) {
  console.log('Form nicht gefunden')
} else {
  console.log('Form gefunden')

  form.addEventListener('submit', async (e) => {
    e.preventDefault()
    console.log('Submit ausgelöst')

    const formData = new FormData(form)

    const response = await fetch('/local-seo', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document
          .querySelector('meta[name="csrf-token"]')
          .getAttribute('content')
      },
      body: formData
    })

    const data = await response.json()
    console.log('Antwort:', data)

    if (data.reportId) {
      window.location.href = `/reports/${data.reportId}`
    }
  })
}
