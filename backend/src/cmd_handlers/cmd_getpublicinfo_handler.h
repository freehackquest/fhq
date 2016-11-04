#ifndef CMD_GETPUBLICINFO_HANDLER_H
#define CMD_GETPUBLICINFO_HANDLER_H

#include "../interfaces/icmdhandler.h"
#include "../interfaces/iwebsocketserver.h"

#include <QString>
#include <QVariant>

class CmdGetPublicInfoHandler : public ICmdHandler {
	public:
		virtual QString cmd();
		virtual bool accessUnauthorized();
		virtual bool accessUser();
		virtual bool accessTester();
		virtual bool accessAdmin();
		virtual void handle(QWebSocket *pClient, IWebSocketServer *pWebSocketServer, QJsonObject obj);
};

#endif // CMD_GETPUBLICINFO_HANDLER_H
