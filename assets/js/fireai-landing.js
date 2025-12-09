/**
 * FireAI Landing Page - JavaScript
 * Đã chuyển đổi từ Haravan Liquid template
 */

let fireaiConfig = {};

// Load config từ JSON file
async function loadConfig() {
    try {
        const response = await fetch('config/fireai-config.json');
        fireaiConfig = await response.json();
        renderPage();
    } catch (error) {
        console.error('Lỗi khi load config:', error);
        // Fallback với config mặc định
        fireaiConfig = getDefaultConfig();
        renderPage();
    }
}

// Config mặc định (fallback)
function getDefaultConfig() {
    return {
        hero: {
            title: "TẮT BÁO ĐỘNG GIẢ, BẬT PHẢN ỨNG THẬT",
            subtitle: "Bảo vệ ngôi nhà với cảnh báo có địa chỉ, truyền tin đa kênh và lắp đặt nhanh.",
            // ... thêm các giá trị mặc định khác
        }
    };
}

// Render toàn bộ trang
function renderPage() {
    renderHero();
    renderVideo();
    renderTrustBadges();
    renderCertification();
    renderFeatures();
    renderProblemSolution();
    renderFAQ();
    renderCTA();
    initInteractions();
}

// Render Hero Section
function renderHero() {
    const hero = fireaiConfig.hero;
    if (!hero || !hero.enable) return;

    // Title
    const titleEl = document.getElementById('hero-title');
    if (titleEl && hero.title) {
        titleEl.textContent = hero.title;
    }

    // Subtitle
    const subtitleEl = document.getElementById('hero-subtitle');
    if (subtitleEl) {
        if (hero.subtitle) {
            subtitleEl.textContent = hero.subtitle;
        } else {
            subtitleEl.style.display = 'none';
        }
    }

    // Stat Text
    const statTextEl = document.getElementById('stat-text');
    if (statTextEl && hero.stat_text) {
        statTextEl.textContent = hero.stat_text;
    }

    // Stat Subtext
    const statSubtextEl = document.getElementById('stat-subtext');
    if (statSubtextEl && hero.stat_subtext) {
        statSubtextEl.textContent = hero.stat_subtext;
    }

    // CTA Buttons
    const ctaButtonsEl = document.getElementById('hero-cta-buttons');
    if (ctaButtonsEl) {
        if (hero.cta_primary_text) {
            const primaryBtn = document.createElement('a');
            primaryBtn.className = 'btn-hero-primary';
            primaryBtn.href = hero.cta_primary_link || '#fireai-form';
            primaryBtn.textContent = hero.cta_primary_text;
            ctaButtonsEl.appendChild(primaryBtn);
        }

        if (hero.video_id && hero.cta_secondary_text) {
            const secondaryBtn = document.createElement('a');
            secondaryBtn.className = 'btn-hero-secondary';
            secondaryBtn.href = hero.cta_secondary_link || '#fireai-video';
            secondaryBtn.textContent = hero.cta_secondary_text;
            ctaButtonsEl.appendChild(secondaryBtn);
        }
    }

    // USP List
    const uspListEl = document.getElementById('hero-usps');
    if (uspListEl && hero.usp_enable && hero.usps && hero.usps.length > 0) {
        hero.usps.forEach(usp => {
            if (usp) {
                const li = document.createElement('li');
                li.textContent = usp;
                uspListEl.appendChild(li);
            }
        });
    } else if (uspListEl) {
        uspListEl.style.display = 'none';
    }

    // Product Title
    const productTitleEl = document.getElementById('product-title');
    if (productTitleEl && hero.product_title) {
        productTitleEl.textContent = hero.product_title;
    }

    // Product Tagline
    const productTaglineEl = document.getElementById('product-tagline');
    if (productTaglineEl && hero.product_tagline) {
        productTaglineEl.textContent = hero.product_tagline;
    }

    // Hero Image
    const heroImageSectionEl = document.getElementById('fireai-hero-image-section');
    if (heroImageSectionEl) {
        if (hero.image_enable && hero.image_url) {
            heroImageSectionEl.innerHTML = `
                <div class="container">
                    <div class="fireai-hero-image-wrapper">
                        <img src="${hero.image_url}" 
                             alt="Hệ thống báo cháy FireAI" 
                             class="fireai-hero-image"
                             loading="lazy"
                             onerror="this.parentElement.parentElement.parentElement.style.display='none';" />
                    </div>
                </div>
            `;
        } else {
            heroImageSectionEl.style.display = 'none';
        }
    }
}

// Render Video Section
function renderVideo() {
    const video = fireaiConfig.video;
    const hero = fireaiConfig.hero;
    const videoContainerEl = document.getElementById('video-container');
    
    if (!videoContainerEl || !video || !video.enable || !hero.video_id) {
        if (videoContainerEl) {
            videoContainerEl.innerHTML = `
                <div class="video-placeholder">
                    <p>Vui lòng nhập YouTube Video ID trong Config</p>
                </div>
            `;
        }
        return;
    }

    const posterUrl = hero.image_url || 'assets/images/fireai_hero_image.jpg';
    videoContainerEl.setAttribute('data-video-id', hero.video_id);
    videoContainerEl.innerHTML = `
        <div class="video-poster" style="background-image: url('${posterUrl}');">
            <button class="video-play-btn" aria-label="Play video">▶</button>
            ${video.caption ? `<span class="video-caption">${video.caption}</span>` : ''}
        </div>
        <div class="video-iframe-slot" aria-hidden="true"></div>
    `;

    // Video CTA
    const videoCtaEl = document.getElementById('video-cta-inline');
    if (videoCtaEl && video.cta_text) {
        videoCtaEl.innerHTML = `
            <a class="btn-video-cta" href="#fireai-form">${video.cta_text}</a>
        `;
    }

    // Video Bullets
    const videoBulletsEl = document.getElementById('video-bullets');
    if (videoBulletsEl && video.bullets_enable && video.bullets && video.bullets.length > 0) {
        video.bullets.forEach(bullet => {
            if (bullet) {
                const li = document.createElement('li');
                li.textContent = bullet;
                videoBulletsEl.appendChild(li);
            }
        });
    } else if (videoBulletsEl) {
        videoBulletsEl.style.display = 'none';
    }
}

// Render Trust Badges
function renderTrustBadges() {
    const trust = fireaiConfig.trust;
    const trustBadgesEl = document.getElementById('fireai-trust-badges');
    
    if (!trustBadgesEl || !trust || !trust.enable || !trust.badges || trust.badges.length === 0) {
        if (trustBadgesEl) trustBadgesEl.style.display = 'none';
        return;
    }

    trust.badges.forEach(badge => {
        if (badge) {
            const badgeItem = document.createElement('div');
            badgeItem.className = 'trust-badge-item';
            badgeItem.innerHTML = `
                <span class="badge-icon">★</span>
                <span class="badge-text">${badge}</span>
            `;
            trustBadgesEl.appendChild(badgeItem);
        }
    });
}

// Render Certification
function renderCertification() {
    const cert = fireaiConfig.certification;
    const certEl = document.getElementById('fireai-certification');
    
    if (!certEl || !cert || !cert.enable) {
        if (certEl) certEl.style.display = 'none';
        return;
    }

    let itemsHTML = '';
    if (cert.items && cert.items.length > 0) {
        cert.items.forEach(item => {
            if (item.title) {
                itemsHTML += `
                    <div class="cert-item">
                        <div class="cert-icon">${item.icon || '📋'}</div>
                        <div class="cert-content">
                            <h4>${item.title}</h4>
                            <p>${item.text || ''}</p>
                        </div>
                    </div>
                `;
            }
        });
    }

    certEl.innerHTML = `
        <h3 class="cert-title">${cert.title || ''}</h3>
        <p class="cert-subtitle">${cert.subtitle || ''}</p>
        <div class="cert-list">${itemsHTML}</div>
    `;
}

// Render Features
function renderFeatures() {
    const features = fireaiConfig.features;
    const featuresEl = document.getElementById('fireai-features-grid');
    
    if (!featuresEl || !features || !features.enable || !features.items || features.items.length === 0) {
        if (featuresEl) featuresEl.style.display = 'none';
        return;
    }

    features.items.forEach(item => {
        if (item.title) {
            const featureItem = document.createElement('div');
            featureItem.className = 'feature-item';
            featureItem.innerHTML = `
                <div class="feature-icon">${item.icon || '💡'}</div>
                <h4>${item.title}</h4>
                <p>${item.text || ''}</p>
            `;
            featuresEl.appendChild(featureItem);
        }
    });
}

// Render Problem & Solution
function renderProblemSolution() {
    const problem = fireaiConfig.problem;
    const solution = fireaiConfig.solution;
    const problemSectionEl = document.getElementById('fireai-problem');
    
    if (!problemSectionEl || !problem || !problem.enable) {
        if (problemSectionEl) problemSectionEl.style.display = 'none';
        return;
    }

    let problemItemsHTML = '';
    if (problem.items && problem.items.length > 0) {
        problem.items.forEach(item => {
            if (item.title) {
                problemItemsHTML += `
                    <div class="problem-item">
                        <div class="problem-icon">⚠️</div>
                        <div class="problem-content">
                            <h4>${item.title}</h4>
                            <div class="problem-text">${item.text.replace(/\n/g, '<br>')}</div>
                        </div>
                    </div>
                `;
            }
        });
    }

    let solutionItemsHTML = '';
    if (solution && solution.items && solution.items.length > 0) {
        solution.items.forEach(item => {
            if (item.title) {
                solutionItemsHTML += `
                    <div class="solution-item">
                        <div class="solution-icon">💡</div>
                        <div class="solution-content">
                            <h4>${item.title}</h4>
                            <div class="solution-text">${item.text.replace(/\n/g, '<br>')}</div>
                        </div>
                    </div>
                `;
            }
        });
    }

    problemSectionEl.innerHTML = `
        <div class="container">
            <div class="problem-section-header">
                <h2 class="section-title">${problem.title || ''}</h2>
            </div>
            <div class="problem-solution-grid">
                <div class="problem-column">
                    <h3 class="column-title">${problem.column_title || ''}</h3>
                    ${problemItemsHTML}
                </div>
                <div class="solution-column">
                    <h3 class="column-title solution-title">${solution.column_title || ''}</h3>
                    ${solutionItemsHTML}
                </div>
            </div>
        </div>
    `;
}

// Render FAQ
function renderFAQ() {
    const faq = fireaiConfig.faq;
    const faqSectionEl = document.getElementById('fireai-faq');
    
    if (!faqSectionEl || !faq || !faq.enable || !faq.items || faq.items.length === 0) {
        if (faqSectionEl) faqSectionEl.style.display = 'none';
        return;
    }

    let faqItemsHTML = '';
    faq.items.forEach(item => {
        if (item.question) {
            faqItemsHTML += `
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>${item.question}</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            ${item.answer.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
            `;
        }
    });

    faqSectionEl.innerHTML = `
        <div class="container">
            <div class="faq-section-header">
                <h2 class="section-title">${faq.title || ''}</h2>
                <a class="faq-cta-link" href="#fireai-form">Đặt câu hỏi khác</a>
            </div>
            <div class="faq-accordion">
                ${faqItemsHTML}
            </div>
        </div>
    `;
}

// Render CTA Section
function renderCTA() {
    const cta = fireaiConfig.cta;
    const form = fireaiConfig.form;
    const ctaSectionEl = document.getElementById('fireai-cta');
    
    if (!ctaSectionEl || !cta || !cta.enable) {
        if (ctaSectionEl) ctaSectionEl.style.display = 'none';
        return;
    }

    let offersHTML = '';
    if (cta.offers && cta.offers.length > 0) {
        cta.offers.forEach(offer => {
            if (offer) {
                offersHTML += `
                    <li>
                        <span class="offer-icon">✅</span>
                        <span class="offer-text">${offer}</span>
                    </li>
                `;
            }
        });
    }

    const badgeHTML = cta.slot_badge_enable && cta.slot_badge_text
        ? `<div class="cta-slot-badge">${cta.slot_badge_text}</div>`
        : '';

    ctaSectionEl.innerHTML = `
        <div class="container">
            <div class="cta-content">
                <div class="cta-header">
                    <div class="cta-title-wrapper">
                        <h2 class="cta-title">${cta.title || ''}</h2>
                        <div class="cta-title-glow"></div>
                        <div class="cta-flash-effect"></div>
                    </div>
                    <p class="cta-subtitle">${cta.subtitle || ''}</p>
                    ${badgeHTML}
                </div>
                <div class="cta-offer">
                    <h3 class="offer-title">${cta.offer_title || ''}</h3>
                    <ul class="offer-list">
                        ${offersHTML}
                    </ul>
                </div>
                <div class="cta-form-wrapper" id="fireai-form">
                    ${renderForm(form)}
                    <p class="form-privacy-note">Thông tin được bảo mật và chỉ dùng cho mục đích tư vấn.</p>
                </div>
            </div>
        </div>
    `;
}

// Render Form
function renderForm(form) {
    if (!form) return '';

    return `
        <div class="fireai-booking-wrapper">
            <div class="form-title-custom-fireai">
                <h3>🔥 ${form.title || 'Đăng ký tư vấn ngay'}</h3>
            </div>
            <form id="fireai-booking-form" action="${form.action_url || '#'}" data-shop="Báo Cháy Việt">
                <div class="contact-form">
                    <div class="row">
                        <div class="col-12">
                            <div class="input-group">
                                <input required type="text" name="${form.entry_name || 'entry.1'}" id="fireai_full_name" data-valid="full_name" class="form-control" placeholder="${form.placeholder_name || 'Họ và tên'}" aria-describedby="basic-addon1">
                            </div>
                            <p class="full_name-validation field-error"></p>
                        </div>
                        <div class="col-12">
                            <div class="input-group">
                                <input pattern="[0-9]{10,12}" required type="text" name="${form.entry_phone || 'entry.2'}" id="fireai_your_phone" data-valid="your_phone" class="form-control" placeholder="${form.placeholder_phone || 'Số điện thoại'}" aria-describedby="basic-addon1">
                            </div>
                            <p class="your_phone-validation field-error"></p>
                        </div>
                        <div class="col-12">
                            <div class="input-group">
                                <textarea name="${form.entry_content || 'entry.3'}" id="fireai_your_content" data-valid="your_content" class="form-control" placeholder="${form.placeholder_content || 'Nội dung câu hỏi (tùy chọn)'}" aria-describedby="basic-addon1"></textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="button-container-fireai">
                                <button type="submit" id="fireai-btn-booking-send" class="button btn-booking-send">
                                    ${form.button_text || 'Gửi yêu cầu tư vấn'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div id="fireai-success-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); padding-top: 60px;">
            <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; text-align: center; border-radius: 8px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);">
                <span id="fireai-close-modal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                <h3 style="color: #4CAF50;">${form.popup_title || 'Cảm ơn bạn!'}</h3>
                <p style="font-size: 16px; color: #333;">${form.popup_content || 'Chúng tôi sẽ liên hệ với bạn trong thời gian sớm nhất.'}</p>
            </div>
        </div>
    `;
}

// Initialize Interactions
function initInteractions() {
    // FAQ Accordion
    initFAQAccordion();
    
    // Video Player
    initVideoPlayer();
    
    // Form Submission
    initFormSubmission();
}

// FAQ Accordion
function initFAQAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        if (!question) return;
        
        question.addEventListener('click', function() {
            const isActive = item.classList.contains('active');
            
            // Đóng tất cả các item khác
            faqItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Toggle item hiện tại
            if (isActive) {
                item.classList.remove('active');
            } else {
                item.classList.add('active');
            }
        });
    });
}

// Video Player
function initVideoPlayer() {
    const videoContainers = document.querySelectorAll('.video-container');
    videoContainers.forEach(container => {
        const poster = container.querySelector('.video-poster');
        const slot = container.querySelector('.video-iframe-slot');
        const videoId = container.getAttribute('data-video-id');
        if (!poster || !slot || !videoId) return;

        poster.addEventListener('click', () => {
            if (slot.innerHTML.trim() !== '') return;
            const iframe = document.createElement('iframe');
            iframe.src = `https://www.youtube.com/embed/${videoId}?rel=0&modestbranding=1&showinfo=0&controls=1&autoplay=1`;
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            iframe.allowFullscreen = true;
            iframe.className = 'hero-video-iframe';
            iframe.loading = 'lazy';
            slot.appendChild(iframe);
            poster.classList.add('is-hidden');
            slot.setAttribute('aria-hidden', 'false');
        });
    });
}

// Form Submission
function initFormSubmission() {
    const form = document.getElementById('fireai-booking-form');
    if (!form) return;

    const modal = document.getElementById('fireai-success-modal');
    const closeModalButton = document.getElementById('fireai-close-modal');

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const actionUrl = form.getAttribute('action');
        const formData = new FormData(form);

        fetch(actionUrl, {
            method: 'POST',
            body: formData,
            mode: 'no-cors'
        }).then(() => {
            if (modal) modal.style.display = 'block';
            form.reset();
        }).catch(error => {
            console.error('Lỗi khi gửi form:', error);
            alert('Đã có lỗi xảy ra, vui lòng thử lại!');
        });
    });

    if (closeModalButton) {
        closeModalButton.addEventListener('click', function() {
            if (modal) modal.style.display = 'none';
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            if (modal) modal.style.display = 'none';
        }
    });
}

// Load config khi DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadConfig);
} else {
    loadConfig();
}

