// https://vike.dev/onPageTransitionEnd
export { onPageTransitionEnd }

function onPageTransitionEnd(pageContext) {
  document.querySelector('body').classList.remove('page-is-transitioning')
}
