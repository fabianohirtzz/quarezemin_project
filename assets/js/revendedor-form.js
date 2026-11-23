/*
 * Seja um Revendedor: envia a ficha para api/revendedor.php.
 * Se o site estiver num host sem PHP (prévia no GitHub Pages), cai para
 * o WhatsApp com a ficha preenchida, para nenhum contato se perder.
 */
(() => {
  const form = document.getElementById("revendedor-form");
  if (!form) return;
  const status = document.getElementById("revendedor-status");
  const button = form.querySelector('button[type="submit"]');
  const WHATSAPP = "5548999546255";

  const onlyDigits = (v) => (v || "").replace(/\D+/g, "");

  // Máscara leve de CNPJ: 00.000.000/0000-00
  form.cnpj.addEventListener("input", () => {
    const d = onlyDigits(form.cnpj.value).slice(0, 14);
    form.cnpj.value = d
      .replace(/^(\d{2})(\d)/, "$1.$2")
      .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
      .replace(/\.(\d{3})(\d)/, ".$1/$2")
      .replace(/(\d{4})(\d)/, "$1-$2");
  });

  const setStatus = (msg, kind) => {
    status.textContent = msg;
    status.dataset.kind = kind || "";
  };

  const validate = () => {
    for (const el of form.querySelectorAll("[required]")) {
      el.removeAttribute("aria-invalid");
    }
    const invalid = [...form.querySelectorAll("[required]")].find((el) => !el.value.trim());
    if (invalid) {
      invalid.setAttribute("aria-invalid", "true");
      invalid.focus();
      return "Preencha os campos obrigatórios.";
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.value.trim())) {
      form.email.setAttribute("aria-invalid", "true");
      form.email.focus();
      return "Confira o e-mail informado.";
    }
    const tel = onlyDigits(form.whatsapp.value);
    if (tel.length < 10 || tel.length > 13) {
      form.whatsapp.setAttribute("aria-invalid", "true");
      form.whatsapp.focus();
      return "Confira o número de WhatsApp com DDD.";
    }
    const cnpj = onlyDigits(form.cnpj.value);
    if (cnpj && cnpj.length !== 14) {
      form.cnpj.setAttribute("aria-invalid", "true");
      form.cnpj.focus();
      return "O CNPJ precisa ter 14 números.";
    }
    return "";
  };

  const whatsappFallback = (data) => {
    const msg = [
      "*Seja um Revendedor · Ficha*",
      "",
      `*Nome:* ${data.nome}`,
      `*Empresa:* ${data.empresa}`,
      `*CNPJ:* ${data.cnpj || "-"}`,
      `*WhatsApp:* ${data.whatsapp}`,
      `*E-mail:* ${data.email}`,
      `*Cidade:* ${data.cidade}/${data.uf}`,
      `*Tipo:* ${data.tipo}`,
      `*Mensagem:* ${data.mensagem || "-"}`,
    ].join("\n");
    window.open(`https://api.whatsapp.com/send?phone=${WHATSAPP}&text=${encodeURIComponent(msg)}`, "_blank", "noopener");
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const error = validate();
    if (error) return setStatus(error, "error");

    const data = Object.fromEntries(new FormData(form).entries());
    button.disabled = true;
    setStatus("Enviando a sua ficha...", "");

    let res;
    try {
      res = await fetch(form.getAttribute("action"), {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(data),
      });
    } catch (_) {
      res = null;
    }

    let body = null;
    if (res) {
      try { body = await res.json(); } catch (_) { body = null; }
    }

    button.disabled = false;

    if (body && body.ok) {
      form.reset();
      setStatus("Ficha recebida! Nossa equipe vai entrar em contato em breve.", "ok");
      return;
    }
    if (body && body.error) {
      setStatus(body.error, "error");
      return;
    }
    // Sem backend (host estático) ou falha de rede: segue pelo WhatsApp.
    whatsappFallback(data);
    setStatus("Abrimos o WhatsApp com a sua ficha preenchida. É só enviar.", "ok");
  });
})();
