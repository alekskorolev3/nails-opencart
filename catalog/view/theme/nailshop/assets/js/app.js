
// function catalog() {
//   if (window.innerWidth > 1024) {
//     document.querySelector('.catalog-container').appendChild()
//   }
// }

document.addEventListener("DOMContentLoaded", function() {
  const carousel = document.querySelector(".carousel");
  const arrowBtns = document.querySelectorAll(".navbutton");
  const wrapper = document.querySelector(".wrapper");


  const firstCard = carousel.querySelector(".card");
  const firstCardWidth = firstCard.offsetWidth;

  let isDragging = false,
    startX,
    startScrollLeft,
    timeoutId;

  const dragStart = (e) => {
    isDragging = true;
    carousel.classList.add("dragging");
    startX = e.pageX;
    startScrollLeft = carousel.scrollLeft;
  };

  const dragging = (e) => {
    if (!isDragging) return;

    // Calculate the new scroll position
    const newScrollLeft = startScrollLeft - (e.pageX - startX);

    // Check if the new scroll position exceeds
    // the carousel boundaries
    if (newScrollLeft <= 0 || newScrollLeft >=
      carousel.scrollWidth - carousel.offsetWidth) {

      // If so, prevent further dragging
      isDragging = false;
      return;
    }

    // Otherwise, update the scroll position of the carousel
    carousel.scrollLeft = newScrollLeft;
  };

  const dragStop = () => {
    isDragging = false;
    carousel.classList.remove("dragging");
  };

  const autoPlay = () => {

    // Return if window is smaller than 800
    if (window.innerWidth < 800) return;

    // Calculate the total width of all cards
    const totalCardWidth = carousel.scrollWidth;

    // Calculate the maximum scroll position
    const maxScrollLeft = totalCardWidth - carousel.offsetWidth;

    // If the carousel is at the end, stop autoplay
    if (carousel.scrollLeft >= maxScrollLeft) return;

    // Autoplay the carousel after every 2500ms
    timeoutId = setTimeout(() =>
      carousel.scrollLeft += firstCardWidth, 2500);
  };

  carousel.addEventListener("mousedown", dragStart);
  carousel.addEventListener("mousemove", dragging);
  document.addEventListener("mouseup", dragStop);
  wrapper.addEventListener("mouseenter", () =>
    clearTimeout(timeoutId));
  wrapper.addEventListener("mouseleave", autoPlay);

  // Add event listeners for the arrow buttons to
  // scroll the carousel left and right
  arrowBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      carousel.scrollLeft += btn.id === "left" ?
        -firstCardWidth : firstCardWidth;
    });
  });
});


document.addEventListener("DOMContentLoaded", function() {
  const carousel = document.querySelector(".carousel-promotion");
  const arrowBtns = document.querySelectorAll(".wrapper-arrow-icon-promotion");
  const wrapper = document.querySelector(".wrapper-promotion");

  const firstCard = carousel.querySelector(".card-promotion");
  const firstCardWidth = firstCard.offsetWidth;

  let isDragging = false,
    startX,
    startScrollLeft,
    timeoutId;

  const dragStart = (e) => {
    isDragging = true;
    carousel.classList.add("dragging");
    startX = e.pageX;
    startScrollLeft = carousel.scrollLeft;
  };

  const dragging = (e) => {
    if (!isDragging) return;

    // Calculate the new scroll position
    const newScrollLeft = startScrollLeft - (e.pageX - startX);

    // Check if the new scroll position exceeds
    // the carousel boundaries
    if (newScrollLeft <= 0 || newScrollLeft >=
      carousel.scrollWidth - carousel.offsetWidth) {

      // If so, prevent further dragging
      isDragging = false;
      return;
    }

    // Otherwise, update the scroll position of the carousel
    carousel.scrollLeft = newScrollLeft;
  };

  const dragStop = () => {
    isDragging = false;
    carousel.classList.remove("dragging");
  };

  const autoPlay = () => {

    // Return if window is smaller than 800
    if (window.innerWidth < 800) return;

    // Calculate the total width of all cards
    const totalCardWidth = carousel.scrollWidth;

    // Calculate the maximum scroll position
    const maxScrollLeft = totalCardWidth - carousel.offsetWidth;

    // If the carousel is at the end, stop autoplay
    if (carousel.scrollLeft >= maxScrollLeft) return;

    // Autoplay the carousel after every 2500ms
    timeoutId = setTimeout(() =>
      carousel.scrollLeft += firstCardWidth, 2500);
  };

  carousel.addEventListener("mousedown", dragStart);
  carousel.addEventListener("mousemove", dragging);
  document.addEventListener("mouseup", dragStop);
  wrapper.addEventListener("mouseenter", () =>
    clearTimeout(timeoutId));
  wrapper.addEventListener("mouseleave", autoPlay);

  // Add event listeners for the arrow buttons to
  // scroll the carousel left and right
  arrowBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      carousel.scrollLeft += btn.id === "left-promotion" ?
        -firstCardWidth : firstCardWidth;
    });
  });
});

document.querySelector('.burger').addEventListener('click', () => {
    const burger = document.querySelector('.burger')
    burger.classList.toggle('toggle')
    const modal = document.querySelector('.modal')
    const modalWrapper = document.querySelector('.modal-wrapper')
    modal.classList.toggle('active')
    modalWrapper.classList.toggle('activeWrapper')
})

document.querySelector('.modal-wrapper').addEventListener('click', () => {
    const modal = document.querySelector('.modal')
    const modalWrapper = document.querySelector('.modal-wrapper')
    modal.classList.toggle('active')
    modalWrapper.classList.toggle('activeWrapper')
})

document.addEventListener('click', (e) => {
  if (e.target !== document.querySelector(".modal-phone")) document.querySelector(".modal-phone").classList.remove('modal-phone-active')
})

document.querySelector('.modal-phone-inner').addEventListener('click', (e) => e.stopPropagation())

document.querySelector('.phone-header').addEventListener('click', (e) => {
  e.stopPropagation()
  const modalPhone = document.querySelector(".modal-phone");
  modalPhone.classList.toggle('modal-phone-active')
})







