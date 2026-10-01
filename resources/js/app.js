const cartDrawer = document.querySelector('#cartDrawer');
const cartItemsElement = document.querySelector('#cartItems');
const cartCountElement = document.querySelector('#cartCount');
const cartEmptyElement = document.querySelector('#cartEmpty');
const checkoutElement = document.querySelector('#drawerCheckout');
const cartTotalElement = document.querySelector('#cartTotal');
const checkoutForm = document.querySelector('#checkoutForm');
const checkoutMessage = document.querySelector('#checkoutMessage');
const checkoutSubmit = document.querySelector('#checkoutSubmit');
const cartOpener = document.querySelector('#openCart');
const cartBackdrop = document.querySelector('#closeCartBackdrop');
const reservationModal = document.querySelector('#reservationModalBackdrop');
const reservationModalClose = document.querySelector('.reservation-modal-close');
const reservationModalOpener = document.querySelector('[data-open-reservation-modal]');
const toastElement = document.querySelector('#siteToast');
let reservationModalIsOpen = false;
const currencyFormatter = new Intl.NumberFormat('id-ID', {
	style: 'currency',
	currency: 'IDR',
	minimumFractionDigits: 0,
	maximumFractionDigits: 2,
});
const storageKey = 'toko-kerabat-cart-v2';
let cart = [];
let toastTimeout;

try {
	const storedCart = JSON.parse(localStorage.getItem(storageKey) ?? '[]');
	cart = Array.isArray(storedCart)
		? storedCart.filter((item) => Number.isInteger(item.id)
			&& Number.isInteger(item.price)
			&& Number.isInteger(item.quantity)
			&& item.quantity > 0
			&& typeof item.name === 'string'
			&& typeof item.image === 'string'
			&& item.image.startsWith('https://images.unsplash.com/'))
		: [];
} catch {
	cart = [];
}

function showToast(message) {
	toastElement.textContent = message;
	toastElement.classList.add('is-visible');
	window.clearTimeout(toastTimeout);
	toastTimeout = window.setTimeout(() => toastElement.classList.remove('is-visible'), 3000);
}

function setDrawerOpen(isOpen) {
	cartDrawer.classList.toggle('is-open', isOpen);
	cartDrawer.setAttribute('aria-hidden', String(!isOpen));
	cartDrawer.inert = !isOpen;
	cartBackdrop.hidden = !isOpen;
	document.body.classList.toggle('drawer-open', isOpen);
	cartOpener.setAttribute('aria-expanded', String(isOpen));

	if (isOpen) {
		document.querySelector('#closeCart').focus();
	} else {
		cartOpener.focus();
	}
}

function setReservationModalOpen(isOpen) {
	if (!reservationModal) {
		return;
	}

	if (reservationModalIsOpen === isOpen) {
		return;
	}

	reservationModalIsOpen = isOpen;
	reservationModal.hidden = !isOpen;
	reservationModal.setAttribute('aria-hidden', String(!isOpen));
	document.body.classList.toggle('drawer-open', isOpen);

	if (isOpen) {
		reservationModalClose?.focus();
	} else {
		reservationModalOpener?.focus();
	}
}

function createQuantityButton(label, symbol, onClick) {
	const button = document.createElement('button');
	button.type = 'button';
	button.setAttribute('aria-label', label);
	button.textContent = symbol;
	button.addEventListener('click', onClick);

	return button;
}

function renderCart() {
	cartItemsElement.replaceChildren();

	const itemCount = cart.reduce((total, item) => total + item.quantity, 0);
	const totalPrice = cart.reduce((total, item) => total + item.price * item.quantity, 0) / 100;

	cartCountElement.textContent = String(itemCount);
	cartEmptyElement.hidden = cart.length > 0;
	checkoutElement.hidden = cart.length === 0;
	cartTotalElement.textContent = currencyFormatter.format(totalPrice);
	try {
		localStorage.setItem(storageKey, JSON.stringify(cart));
	} catch {
		// The in-memory cart remains usable when browser storage is unavailable.
	}

	cart.forEach((item) => {
		const row = document.createElement('article');
		row.className = 'cart-row';

		const image = document.createElement('img');
		image.src = item.image;
		image.alt = '';
		image.width = 70;
		image.height = 70;

		const details = document.createElement('div');
		const name = document.createElement('h3');
		name.textContent = item.name;

		const price = document.createElement('div');
		price.className = 'cart-row-price';
		price.textContent = currencyFormatter.format(item.price / 100);

		const controls = document.createElement('div');
		controls.className = 'quantity-controls';
		controls.append(
			createQuantityButton(`Kurangi ${item.name}`, '-', () => changeQuantity(item.id, -1)),
		);

		const quantity = document.createElement('span');
		quantity.textContent = String(item.quantity);
		controls.append(quantity);
		controls.append(
			createQuantityButton(`Tambah ${item.name}`, '+', () => changeQuantity(item.id, 1)),
		);

		details.append(name, price, controls);

		const lineTotal = document.createElement('strong');
		lineTotal.className = 'cart-line-total';
		lineTotal.textContent = currencyFormatter.format(item.price * item.quantity / 100);

		row.append(image, details, lineTotal);
		cartItemsElement.append(row);
	});
}

function changeQuantity(menuId, amount) {
	const item = cart.find((cartItem) => cartItem.id === menuId);

	if (!item) {
		return;
	}

	item.quantity += amount;
	cart = cart.filter((cartItem) => cartItem.quantity > 0);
	renderCart();
}

document.querySelectorAll('[data-add-to-cart]').forEach((button) => {
	button.addEventListener('click', () => {
		const menuId = Number(button.dataset.menuId);
		const item = cart.find((cartItem) => cartItem.id === menuId);

		if (item) {
			item.quantity += 1;
		} else {
			cart.push({
				id: menuId,
				name: button.dataset.menuName,
				price: Number(button.dataset.menuPrice),
				image: button.dataset.menuImage,
				quantity: 1,
			});
		}

		renderCart();
		showToast(`${button.dataset.menuName} ditambahkan ke tas pesanan.`);
	});
});

document.querySelectorAll('[data-filter]').forEach((button) => {
	button.addEventListener('click', () => {
		const category = button.dataset.filter;

		document.querySelectorAll('[data-filter]').forEach((filterButton) => {
			const isActive = filterButton === button;
			filterButton.classList.toggle('is-active', isActive);
			filterButton.setAttribute('aria-pressed', String(isActive));
		});

		document.querySelectorAll('.menu-card').forEach((card) => {
			card.hidden = category !== 'all' && card.dataset.category !== category;
		});
	});
});

cartOpener?.addEventListener('click', () => setDrawerOpen(true));
document.querySelector('#closeCart')?.addEventListener('click', () => setDrawerOpen(false));
cartBackdrop?.addEventListener('click', () => setDrawerOpen(false));
reservationModalOpener?.addEventListener('click', (event) => {
	event.preventDefault();
	event.stopPropagation();
	setReservationModalOpen(true);
});
reservationModalClose?.addEventListener('click', (event) => {
	event.preventDefault();
	event.stopPropagation();
	setReservationModalOpen(false);
});
reservationModal?.addEventListener('click', (event) => {
	if (event.target === reservationModal) {
		event.stopPropagation();
		setReservationModalOpen(false);
	}
});

document.querySelectorAll('[data-reservation-status-form]').forEach((form) => {
	form.addEventListener('submit', async (event) => {
		event.preventDefault();

		const select = form.querySelector('select[name="status"]');
		const button = form.querySelector('button[type="submit"]');
		const statusBadge = form.closest('tr')?.querySelector('.reservation-pill');
		const previousLabel = button.textContent;
		const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

		button.disabled = true;
		button.textContent = 'Menyimpan...';

		try {
			const response = await fetch(form.action, {
				method: 'PATCH',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrfToken,
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify({ status: select.value }),
			});

			if (!response.ok) {
				throw new Error('Gagal memperbarui status reservasi.');
			}

			const data = await response.json();
			const statusMap = {
				menunggu: 'Menunggu',
				diproses: 'Diproses',
				selesai: 'Selesai',
				dibatalkan: 'Dibatalkan',
			};
			const nextStatus = statusMap[data.status] ?? data.status;

			if (statusBadge) {
				statusBadge.textContent = nextStatus;
				statusBadge.className = `reservation-pill reservation-status-${data.status}`;
			}

			showToast(data.message ?? 'Status reservasi diperbarui.');
		} catch (error) {
			console.error(error);
			showToast('Status reservasi gagal diperbarui.');
		} finally {
			button.disabled = false;
			button.textContent = previousLabel;
		}
	});
});

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		if (cartDrawer.classList.contains('is-open')) {
			setDrawerOpen(false);
			return;
		}

		if (!reservationModal?.hidden) {
			setReservationModalOpen(false);
		}
	}
});

const recoveryDeleteModal = document.querySelector('#recoveryDeleteModal');
const recoveryDeleteConfirm = document.querySelector('.recovery-delete-confirm');
const recoveryDeleteCancel = document.querySelector('.recovery-delete-cancel');
let pendingRecoveryDeleteUrl = null;

document.querySelectorAll('.recovery-delete-single').forEach((button) => {
	button.addEventListener('click', () => {
		pendingRecoveryDeleteUrl = button.dataset.deleteRoute;
		recoveryDeleteModal.hidden = false;
	});
});

recoveryDeleteCancel?.addEventListener('click', () => {
	recoveryDeleteModal.hidden = true;
	pendingRecoveryDeleteUrl = null;
});

recoveryDeleteConfirm?.addEventListener('click', () => {
	if (!pendingRecoveryDeleteUrl) {
		recoveryDeleteModal.hidden = true;
		return;
	}

	const form = document.createElement('form');
	form.method = 'post';
	form.action = pendingRecoveryDeleteUrl;
	form.style.display = 'none';
	form.innerHTML = `
		<input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''}">
		<input type="hidden" name="_method" value="DELETE">
	`;
	document.body.appendChild(form);
	form.submit();
});

const mobileMenuToggle = document.querySelector('#mobileMenuToggle');
const primaryNavigation = document.querySelector('#primaryNavigation');

mobileMenuToggle?.addEventListener('click', () => {
	const isOpen = mobileMenuToggle.getAttribute('aria-expanded') !== 'true';
	mobileMenuToggle.setAttribute('aria-expanded', String(isOpen));
	mobileMenuToggle.setAttribute('aria-label', isOpen ? 'Tutup navigasi' : 'Buka navigasi');
	primaryNavigation?.classList.toggle('is-open', isOpen);
});

primaryNavigation?.querySelectorAll('a').forEach((link) => {
	link.addEventListener('click', () => {
		mobileMenuToggle?.setAttribute('aria-expanded', 'false');
		mobileMenuToggle?.setAttribute('aria-label', 'Buka navigasi');
		primaryNavigation.classList.remove('is-open');
	});
});

checkoutForm?.addEventListener('submit', async (event) => {
	event.preventDefault();
	checkoutMessage.textContent = '';
	checkoutSubmit.disabled = true;
	checkoutSubmit.textContent = 'Mengirim pesanan...';

	try {
		const response = await fetch(checkoutForm.action, {
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
			},
			body: JSON.stringify({
				nama: checkoutForm.elements.nama.value,
				email: checkoutForm.elements.email.value || null,
				no_telepon: checkoutForm.elements.no_telepon.value,
				items: cart.map((item) => ({ menu_id: item.id, quantity: item.quantity })),
			}),
		});
		const result = await response.json();

		if (!response.ok) {
			const firstError = Object.values(result.errors ?? {}).flat()[0];
			checkoutMessage.textContent = firstError ?? result.message ?? 'Pesanan belum dapat dikirim. Coba kembali.';
			return;
		}

		cart = [];
		renderCart();
		checkoutForm.reset();
		setDrawerOpen(false);
		showToast(`Pesanan ${result.code} berhasil dikirim. Terima kasih!`);
	} catch {
		checkoutMessage.textContent = 'Koneksi terputus. Periksa jaringan lalu coba kembali.';
	} finally {
		checkoutSubmit.disabled = false;
		checkoutSubmit.innerHTML = 'Kirim pesanan <span aria-hidden="true">&rarr;</span>';
	}
});

const revealElements = document.querySelectorAll('.menu-card, .section-heading, .story-copy, .reservation-form-wrap');

if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
	const revealObserver = new IntersectionObserver((entries, observer) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				entry.target.classList.add('is-visible');
				observer.unobserve(entry.target);
			}
		});
	}, { threshold: 0.12 });

	revealElements.forEach((element) => {
		element.classList.add('reveal');
		revealObserver.observe(element);
	});
}

renderCart();
