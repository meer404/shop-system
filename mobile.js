// Minimal sidebar toggle for mobile
document.addEventListener('click', function(e){
  if(e.target.closest('.mobile-menu-toggle')){
    document.querySelector('.sidebar')?.classList.toggle('active');
    document.body.classList.toggle('nav-open');
  }
});