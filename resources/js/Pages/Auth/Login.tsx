import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Leaf, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import type { BackendSharedProps } from '../../backend-types';
import { Brand, Button, Field } from '../../components/ui';

export default function Login() {
    const { routes, flash } = usePage<BackendSharedProps>().props;
    const form = useForm({ email: '', password: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(routes.loginStore, { onFinish: () => form.reset('password') });
    }

    return (
        <div className="login-page">
            <Head title="Masuk" />
            <aside className="login-brand-panel">
                <Brand />
                <div className="login-story">
                    <span className="login-story-tag"><Leaf size={13} />DARI ALAM, UNTUK KESEHATAN</span>
                    <h1>Proses lebih terarah.<br />Produk lebih dipercaya.</h1>
                    <p>Satu ruang kerja untuk mendampingi produk kesehatan Anda, dari pengajuan hingga izin edar.</p>
                    <div className="login-plant"><Leaf size={150} strokeWidth={0.7} /></div>
                    <div className="login-assurance"><ShieldCheck size={21} /><span>Dokumen terkelola. Tim terhubung.<br />Setiap langkah dapat ditelusuri.</span></div>
                </div>
                <small>PT Aisha Zahra Herbalaboratories</small>
            </aside>
            <main className="login-main">
                <span className="login-mobile-brand"><Brand /></span>
                <div className="login-card">
                    <span className="eyebrow">SELAMAT DATANG KEMBALI</span>
                    <h2>Masuk ke ruang kerja Anda</h2>
                    <p>Gunakan akun staf yang disiapkan administrator.</p>
                    {flash.status && <p role="status" className="info-box mb-5">{flash.status}</p>}
                    <form onSubmit={submit} className="form-stack">
                        <Field label="Email" error={form.errors.email}><input name="email" type="email" autoComplete="username" required autoFocus value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} placeholder="nama@perusahaan.com" /></Field>
                        <Field label="Kata sandi" error={form.errors.password}><input name="password" type="password" autoComplete="current-password" required value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} /></Field>
                        <Button type="submit" icon={ArrowRight} disabled={form.processing}>{form.processing ? 'Memproses...' : 'Masuk'}</Button>
                    </form>
                    <div className="login-demo-note"><ShieldCheck size={16} /><p>Akses mengikuti role akun Anda. Sesi berakhir setelah 60 menit tidak aktif.</p></div>
                </div>
            </main>
        </div>
    );
}
