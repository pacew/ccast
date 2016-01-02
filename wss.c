#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>

#include <libwebsockets.h>

int wss_port;
char *key_file, *crt_file, *chain_file;

void
usage (void)
{
	fprintf (stderr, "usage: wss\n");
	exit (1);
}

void
read_conf (void)
{
	FILE *f;
	char buf[1000];
	char op[1000], op2[1000];
	int idx;
	char *val;
	char *p;

	if ((f = fopen ("TMP.conf", "r")) == NULL)
		return;

	while (fgets (buf, sizeof buf, f) != NULL) {
		if (sscanf (buf, "%s %n", op, &idx) != 1)
			continue;
		val = buf + idx;
		while (isspace (*val))
			val++;
		idx = strlen (val);
		while (idx > 0 && isspace (val[idx - 1]))
			val[--idx] = 0;

		if (strcmp (op, "SetEnv") == 0) {
			if (sscanf (val, "%s %n", op2, &idx) != 1)
				continue;
			val += idx;
			printf ("%s : %s\n", op2, val);
			if (strcmp (op2, "wss_port") == 0) {
				p = val;
				while (*p && ! isdigit (*p))
					p++;
				wss_port = atoi (p);
			}
		} else if (strcmp (op, "SSLCertificateKeyFile") == 0) {
			key_file = strdup (val);
		} else if (strcmp (op, "SSLCertificateFile") == 0) {
			crt_file = strdup (val);
		} else if (strcmp (op, "SSLCertificateChainFile") == 0) {
			chain_file = strdup (val);
		}
	}

	fclose (f);
}

struct work {
	struct work *next;
	struct work *prev;
	int fd;
	int events;
	void (*callback)(struct work *wp, int revents);
};

struct work work_head;
int work_count;

struct work *
add_work (int fd, int events)
{
	struct work *wp;

	if ((wp = calloc (1, sizeof *wp)) == NULL) {
		printf ("out of memory\n");
		exit (1);
	}

	wp->fd = fd;
	wp->events = events;

	wp->next = &work_head;
	wp->prev = work_head.prev;
	work_head.prev->next = wp;
	work_head.prev = wp;
	work_count++;
	
	return (wp);
}

struct work *
find_work (int fd)
{
	struct work *wp;

	for (wp = work_head.next; wp != &work_head; wp = wp->next) {
		if (wp->fd == fd) {
			return (wp);
		}
	}
	return (NULL);
}

void
delete_work (struct work *wp)
{
	wp->next->prev = wp->prev;
	wp->prev->next = wp->next;
	free (wp);
	work_count--;
}

int
callback_http (struct lws *wsi,
	       enum lws_callback_reasons reason,
	       void *user,
	       void *in, size_t len)
{
	struct work *wp;
	struct lws_pollargs *pollargs;
	char *url;
	unsigned char obuf[LWS_SEND_BUFFER_PRE_PADDING + 1000];
	unsigned char *ubuf, *p, *end;

	printf ("callback %d\n", reason);
	switch (reason) {
	case LWS_CALLBACK_PROTOCOL_INIT:
		printf ("cb: protocol init (%p %p %p %ld)\n",
			wsi, user, in, len);
		break;

	case LWS_CALLBACK_HTTP:
		printf ("cb: http (%p %p %p %ld)\n",
			wsi, user, in, len);
		url = in;
		printf ("url = %s\n", url);

		end = obuf + sizeof obuf;
		ubuf = obuf + LWS_SEND_BUFFER_PRE_PADDING;
		p = ubuf;
		if (lws_add_http_header_status (wsi, 200, &p, end))
			return (1);

		if (lws_add_http_header_content_length (wsi, 3, &p, end))
			return (1);

		if (lws_finalize_http_header (wsi, &p, end))
			return (1);

		if (lws_write (wsi, ubuf, p - ubuf, LWS_WRITE_HTTP_HEADERS) < 0)
			return (-1);

		lws_callback_on_writable (wsi);

		break;

	case LWS_CALLBACK_HTTP_WRITEABLE:
		printf ("writable: write 3\n");
		end = obuf + sizeof obuf;
		ubuf = obuf + LWS_SEND_BUFFER_PRE_PADDING;
		p = ubuf;
		strcpy ((char *)p, "foo");
		p += 3;
		lws_write (wsi, ubuf, p - ubuf, LWS_WRITE_HTTP);
		return (0);

	case LWS_CALLBACK_ADD_POLL_FD:
		pollargs = in;
		printf ("poll add %d\n", pollargs->fd);
		wp = add_work (pollargs->fd, pollargs->events);
		break;

	case LWS_CALLBACK_DEL_POLL_FD:
		pollargs = in;
		printf ("poll delete %d\n", pollargs->fd);
		if ((wp = find_work (pollargs->fd)) != NULL)
			delete_work (wp);
		break;

	case LWS_CALLBACK_CHANGE_MODE_POLL_FD:
		pollargs = in;
		if ((wp = find_work (pollargs->fd)) != NULL) {
			printf ("sock %d: change events from 0x%x to 0x%x\n",
				wp->fd, wp->events, pollargs->events);
			wp->events = pollargs->events;
		}
		break;

	case LWS_CALLBACK_PROTOCOL_DESTROY:
		printf ("protocol destroy\n");
		exit (1);

	case LWS_CALLBACK_LOCK_POLL:
	case LWS_CALLBACK_UNLOCK_POLL:
	case LWS_CALLBACK_GET_THREAD_ID:
	case LWS_CALLBACK_FILTER_NETWORK_CONNECTION:
	case LWS_CALLBACK_WSI_CREATE:
	case LWS_CALLBACK_WSI_DESTROY:
	case LWS_CALLBACK_SERVER_NEW_CLIENT_INSTANTIATED:
	case LWS_CALLBACK_CLOSED_HTTP:
	case LWS_CALLBACK_FILTER_HTTP_CONNECTION:
		return (0);

	default:
		printf ("unknown callback %d  (%p %p %p %ld)\n",
			reason, wsi, user, in, len);
		exit (1);
		break;
	}
	return (0);
}


struct lws_protocols protocols[] = {
	{ "http", callback_http },
};

struct lws_context *context;

void
socket_setup (void)
{
	struct lws_context_creation_info info;
	char fname[1000];
	int fd;
	FILE *inf, *outf;
	int c;
	int lastc;

	memset (&info, 0, sizeof info);
	info.port = wss_port;
	info.protocols = protocols;
	info.uid = -1;
	info.gid = -1;


	if (crt_file && key_file && chain_file) {
		strcpy (fname, "/tmp/crt.XXXXXX");
		if ((fd = mkstemp (fname)) < 0) {
			fprintf (stderr, "can't create tmp file %s\n", fname);
			exit (1);
		}
		outf = fdopen (fd, "w");

		if ((inf = fopen (crt_file, "r")) == NULL) {
			fprintf (stderr, "can't open %s\n", crt_file);
			exit (1);
		}
		lastc = 0;
		while ((c = getc (inf)) != EOF) {
			putc (c, outf);
			lastc = c;
		}
		fclose (inf);

		if (lastc != '\n')
			putc ('\n', outf);
		
		if ((inf = fopen (chain_file, "r")) == NULL) {
			fprintf (stderr, "can't open %s\n", chain_file);
			exit (1);
		}
		while ((c = getc (inf)) != EOF)
			putc (c, outf);
		fclose (inf);

		fclose (outf);

		info.ssl_cert_filepath = fname;
		info.ssl_private_key_filepath = key_file;
	}
	
	context = lws_create_context (&info);
}

struct pollfd *pollfds;
int pollfds_avail;

void
work_stdin (struct work *wp, int revents)
{
	char buf[1000];
	int len;

	while (1) {
		if (fgets (buf, sizeof buf, stdin) == NULL)
			break;

		len = strlen (buf);
		while (len > 0 && isspace (buf[len-1]))
			buf[--len] = 0;

		printf ("got: %s\n", buf);
	}
}

int
main (int argc, char **argv)
{
	int c;
	struct work *wp;
	struct pollfd *pf;
	int timeout_msecs;
	int debug_level = 7;

	while ((c = getopt (argc, argv, "d:")) != EOF) {
		switch (c) {
		case 'd':
			debug_level = atoi (optarg);
			break;
		default:
			usage ();
		}
	}

	if (optind != argc)
		usage ();

	lws_set_log_level(debug_level, NULL);

	work_head.next = &work_head;
	work_head.prev = &work_head;

	wp = add_work (0, POLLIN);
	wp->callback = work_stdin;
	fcntl (0, F_SETFL, O_NONBLOCK);

	read_conf ();

	if (wss_port == 0) {
		printf ("can't find wss_port\n");
		exit (1);
	}

	printf ("wss port %d\n", wss_port);

	socket_setup ();

	while (1) {
		if (work_count > pollfds_avail) {
			pollfds_avail = work_count + 100;
			pollfds = realloc (pollfds,
					   pollfds_avail * sizeof *pollfds);
		}

		pf = pollfds;
		for (wp = work_head.next; wp != &work_head; wp = wp->next) {
			pf->fd = wp->fd;
			pf->events = wp->events;
			pf->revents = 0;
			pf++;
		}

		timeout_msecs = 1000;
		if (poll (pollfds, work_count, timeout_msecs) < 0) {
			fprintf (stderr, "poll error %d: %s\n",
				 errno, strerror (errno));
			exit (1);
		}

		lws_service (context, 0);

		pf = pollfds;
		for (wp = work_head.next; wp != &work_head; wp = wp->next) {
			if (pf->events & pf->revents) {
				if (wp->callback) {
					printf ("stdin active\n");
					(*wp->callback)(wp, pf->revents);
				}
			}
		}
	}

	return (0);
}
