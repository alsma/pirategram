import AuthForm from '@/components/auth/auth-form.jsx'

export { Page }

const Page = () => {

  return <div className="container flex items-center justify-center p-20">
    <div className="w-full max-w-md">
      <div className="panel-texture p-8 rounded-2xl shadow-xl border border-ember/20 ring-2 ring-ember/10">
        <AuthForm defaultPanel="login" />
      </div>
    </div>
  </div>
}