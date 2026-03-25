const testimonials = [
  {
    name: "Stephen Juma",
    role: "Chairman of the Board",
    quote: "At Mashirikiano SACCO, we prioritize member value and long-term sustainability to ensure your financial growth.",
    image: "assets/img/default-profile.svg"
  },
  {
    name: "Micah Mosomi",
    role: "IT Assistant",
    quote: "We leverage modern digital systems to provide you with secure, efficient, and reliable financial services.",
    image: "assets/img/default-profile.svg"
  },
  {
    name: "Vice Chairperson",
    role: "Leadership Team",
    quote: "Our policies are designed to support your development goals while keeping our strategic priorities aligned with your needs.",
    image: "assets/img/default-profile.svg"
  },
  {
    name: "National Treasurer",
    role: "Leadership Team",
    quote: "We ensure strict financial stewardship and accountability to perfectly safeguard all member resources.",
    image: "assets/img/default-profile.svg"
  },
  {
    name: "Honorary Secretary",
    role: "Leadership Team",
    quote: "Transparency and open communication form the bedrock of our administration and member relations.",
    image: "assets/img/default-profile.svg"
  }
];

document.addEventListener('DOMContentLoaded', () => {
  const testimonialsWrapper = document.getElementById('testimonials-wrapper');
  if (testimonialsWrapper) {
    testimonialsWrapper.innerHTML = '';
    testimonials.forEach(testimonial => {
      const slide = document.createElement('div');
      slide.className = 'swiper-slide';
      
      slide.innerHTML = `
        <div class="testimonial-item">
          <img src="${testimonial.image}" class="testimonial-img" alt="${testimonial.name}">
          <h3>${testimonial.name}</h3>
          <h4>${testimonial.role}</h4>
          <p>
            <i class="bi bi-quote quote-icon-left"></i>
            ${testimonial.quote}
            <i class="bi bi-quote quote-icon-right"></i>
          </p>
        </div>
      `;
      testimonialsWrapper.appendChild(slide);
    });
  }
});
