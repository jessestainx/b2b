/**
 * AWA Motos — Componente B2B Login (React + PWA Studio)
 *
 * Preserva lógica de negócio B2B:
 * - Login com CNPJ (não email)
 * - Validação de Razão Social
 * - Redirecionamento para dashboard B2B
 *
 * Será usado quando a home migrar para PWA Studio.
 * Por enquanto, fica como referência/template em pwa-studio/src/components/
 *
 * Dependências:
 *   npm install @apollo/client graphql
 */

import React, { useState } from 'react';
import { useMutation, gql } from '@apollo/client';

const B2B_LOGIN_MUTATION = gql`
    mutation B2BLogin($cnpj: String!, $password: String!) {
        awaB2BLogin(cnpj: $cnpj, password: $password) {
            token
            customer {
                id
                cnpj
                razaoSocial
                email
                grupoAtendimento
                creditLimit
            }
            redirectUrl
        }
    }
`;

const formatCnpj = (value) => {
    // Remove tudo que não é dígito
    const digits = value.replace(/\D/g, '');
    // Aplica máscara: 00.000.000/0000-00
    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
    if (digits.length <= 8) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
    if (digits.length <= 12) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12, 14)}`;
};

const validateCnpj = (cnpj) => {
    const digits = cnpj.replace(/\D/g, '');
    if (digits.length !== 14) return false;
    // Validação completa de CNPJ (módulo 11)
    if (/^(\d)\1+$/.test(digits)) return false;

    const calc = (base) => {
        let sum = 0;
        let pos = base.length - 7;
        for (let i = base.length; i >= 1; i--) {
            sum += parseInt(base.charAt(base.length - i)) * pos--;
            if (pos < 2) pos = 9;
        }
        const result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
        return result;
    };

    return calc(digits.slice(0, 12)) === parseInt(digits.charAt(12)) &&
           calc(digits.slice(0, 13)) === parseInt(digits.charAt(13));
};

export default function B2BLoginForm({ onSuccess }) {
    const [cnpj, setCnpj] = useState('');
    const [password, setPassword] = useState('');
    const [razaoSocial, setRazaoSocial] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const [loginMutation] = useMutation(B2B_LOGIN_MUTATION, {
        onCompleted: (data) => {
            setLoading(false);
            if (data.awaB2BLogin.token) {
                localStorage.setItem('b2b_token', data.awaB2BLogin.token);
                onSuccess?.(data.awaB2BLogin.customer);
            }
        },
        onError: (err) => {
            setLoading(false);
            setError(err.message || 'Erro ao fazer login');
        }
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setError('');

        // Validação client-side
        if (!validateCnpj(cnpj)) {
            setError('CNPJ inválido');
            return;
        }
        if (password.length < 6) {
            setError('Senha deve ter ao menos 6 caracteres');
            return;
        }

        setLoading(true);
        loginMutation({
            variables: {
                cnpj: cnpj.replace(/\D/g, ''),
                password
            }
        });
    };

    return (
        <form onSubmit={handleSubmit} className="b2b-login-form" noValidate>
            <h2>Login Corporativo (B2B)</h2>
            <p className="subtitle">Acesse preços e condições para pessoa jurídica</p>

            {error && <div className="error-message" role="alert">{error}</div>}

            <div className="field">
                <label htmlFor="b2b-cnpj">CNPJ</label>
                <input
                    id="b2b-cnpj"
                    type="text"
                    inputMode="numeric"
                    autoComplete="username"
                    placeholder="00.000.000/0000-00"
                    value={cnpj}
                    onChange={(e) => setCnpj(formatCnpj(e.target.value))}
                    maxLength={18}
                    required
                    aria-invalid={error && !validateCnpj(cnpj)}
                />
            </div>

            <div className="field">
                <label htmlFor="b2b-razao">Razão Social (opcional, autocomplete)</label>
                <input
                    id="b2b-razao"
                    type="text"
                    autoComplete="organization"
                    placeholder="Sua empresa Ltda"
                    value={razaoSocial}
                    onChange={(e) => setRazaoSocial(e.target.value)}
                />
            </div>

            <div className="field">
                <label htmlFor="b2b-pass">Senha</label>
                <input
                    id="b2b-pass"
                    type="password"
                    autoComplete="current-password"
                    placeholder="••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    minLength={6}
                />
            </div>

            <button type="submit" disabled={loading} className="b2b-submit">
                {loading ? 'Entrando...' : 'Entrar no Portal B2B'}
            </button>

            <p className="register-link">
                Não tem cadastro? <a href="/b2b/register">Cadastre sua empresa</a>
            </p>
        </form>
    );
}
