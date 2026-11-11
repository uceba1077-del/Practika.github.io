// GameHub · клиентская часть: валидация форм, шторка фильтров, мелочи интерфейса.
// Сервер всё равно проверяет данные ещё раз — это только подсказки до отправки.
(function () {
  'use strict';

  // ---------- правила валидации ----------
  // Поле описывается data-атрибутами: data-required, data-min, data-max,
  // data-type="email|nick|password|time", data-match="#id", data-msg-... для своих текстов.
  var RE = {
    email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
    nick: /^[A-Za-z0-9_]{3,20}$/,
    password: /^(?=.*[A-Za-zА-Яа-я])(?=.*\d).{8,}$/,
    time: /^([01]\d|2[0-3]):[0-5]\d$/
  };
  var MSG = {
    required: 'Заполните это поле',
    email: 'Укажи полный адрес, например name@mail.ru',
    nick: '3–20 символов: латиница, цифры и _',
    password: 'Не меньше 8 символов, буквы и цифры',
    time: 'Время в формате ЧЧ:ММ',
    match: 'Пароли не совпадают'
  };

  function errorBox(field) {
    var fl = field.closest('.fl') || field.parentElement;
    var box = fl.querySelector('.err-t');
    if (!box) {
      box = document.createElement('span');
      box.className = 'err-t';
      fl.appendChild(box);
    }
    return box;
  }

  function check(field) {
    var v = (field.value || '').trim();
    var d = field.dataset;
    var msg = '';
    if (field.type === 'file') {
      var f = field.files && field.files[0];
      if (f) {
        var max = Number(d.maxSize || 2097152);
        if (!/^image\/(png|jpeg)$/.test(f.type)) msg = 'Можно только PNG или JPG до 2 МБ';
        else if (f.size > max) msg = 'Файл больше 2 МБ';
      }
    } else if (field.type === 'checkbox') {
      if (d.required !== undefined && !field.checked) msg = d.msgRequired || 'Нужно отметить';
    } else if (d.required !== undefined && !v) {
      msg = d.msgRequired || MSG.required;
    } else if (v) {
      if (d.type && RE[d.type] && !RE[d.type].test(v)) msg = d['msg' + d.type.charAt(0).toUpperCase() + d.type.slice(1)] || MSG[d.type];
      else if (d.min && v.length < Number(d.min)) msg = d.msgLen || ('Минимум ' + d.min + ' символа');
      else if (d.max && v.length > Number(d.max)) msg = d.msgLen || ('Максимум ' + d.max + ' символов');
      else if (d.match) {
        var other = document.querySelector(d.match);
        if (other && other.value !== field.value) msg = MSG.match;
      }
    }
    var box = errorBox(field);
    box.textContent = msg;
    var target = field.type === 'file' ? field.closest('.file-box') : field;
    if (target) {
      target.classList.toggle('err', !!msg);
      if (field.type !== 'file' && field.type !== 'checkbox') target.classList.toggle('ok', !msg && !!v && d.okMark !== undefined);
    }
    field.setAttribute('aria-invalid', msg ? 'true' : 'false');
    return !msg;
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.setAttribute('novalidate', '');
    var fields = form.querySelectorAll('input, textarea, select');
    fields.forEach(function (f) {
      f.addEventListener('blur', function () { if (f.value) check(f); });
      f.addEventListener('change', function () { check(f); });
      f.addEventListener('input', function () { if (f.getAttribute('aria-invalid') === 'true') check(f); });
    });
    form.addEventListener('submit', function (ev) {
      var ok = true, first = null;
      fields.forEach(function (f) {
        if (!check(f)) { ok = false; first = first || f; }
      });
      // группы «выбери хотя бы одно» (радио/чекбоксы) — data-group-required на контейнере
      form.querySelectorAll('[data-group-required]').forEach(function (g) {
        var picked = g.querySelector('input:checked');
        var box = g.parentElement.querySelector('.err-t');
        if (box) box.textContent = picked ? '' : g.dataset.groupRequired;
        if (!picked) { ok = false; first = first || g.querySelector('input'); }
      });
      if (!ok) {
        ev.preventDefault();
        if (first) first.focus();
      } else {
        var btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.dataset.text = btn.textContent; btn.textContent = 'Отправляем…'; }
      }
    });
  });

  // ---------- счётчики символов ----------
  document.querySelectorAll('[data-counter]').forEach(function (ta) {
    var out = document.querySelector(ta.dataset.counter);
    var max = ta.getAttribute('maxlength') || ta.dataset.max;
    var upd = function () { if (out) out.textContent = ta.value.length + ' / ' + max; };
    ta.addEventListener('input', upd);
    upd();
  });

  // ---------- сила пароля ----------
  document.querySelectorAll('[data-strength]').forEach(function (inp) {
    var bar = document.querySelector(inp.dataset.strength);
    inp.addEventListener('input', function () {
      var v = inp.value, s = 0;
      if (v.length >= 8) s++;
      if (/\d/.test(v) && /[A-Za-zА-Яа-я]/.test(v)) s++;
      if (v.length >= 12 || /[^A-Za-z0-9А-Яа-я]/.test(v)) s++;
      if (bar) bar.dataset.s = v ? String(Math.max(1, s)) : '0';
    });
  });

  // ---------- показать пароль ----------
  document.querySelectorAll('[data-toggle-pass]').forEach(function (b) {
    b.addEventListener('click', function () {
      var inp = document.querySelector(b.dataset.togglePass);
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      b.textContent = show ? 'скрыть' : 'показать';
    });
  });

  // ---------- шторка фильтров на телефоне ----------
  var filters = document.querySelector('.filters');
  if (filters) {
    var shade;
    var open = function () {
      filters.classList.add('open');
      shade = document.createElement('div');
      shade.className = 'shade';
      shade.addEventListener('click', close);
      document.body.appendChild(shade);
    };
    var close = function () {
      filters.classList.remove('open');
      if (shade) shade.remove();
    };
    document.querySelectorAll('.filters-open').forEach(function (b) { b.addEventListener('click', open); });
    document.querySelectorAll('.filters-close').forEach(function (b) { b.addEventListener('click', close); });
  }

  // ---------- подтверждение опасных действий ----------
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.dataset.confirm)) ev.preventDefault();
    });
  });

  // ---------- закрыть сообщение ----------
  document.querySelectorAll('.flash-x').forEach(function (b) {
    b.addEventListener('click', function () { b.parentElement.remove(); });
  });

  // ---------- автоотправка фильтров при изменении сортировки ----------
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });
})();
